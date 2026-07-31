import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const lifecycleSource = fs.readFileSync(
  new URL('../public/assets/js/display-media-lifecycle.js', import.meta.url),
  'utf8'
);
const slideshowSource = fs.readFileSync(
  new URL('../public/assets/js/slideshow.js', import.meta.url),
  'utf8'
);
const displayCss = fs.readFileSync(
  new URL('../public/assets/css/display.css', import.meta.url),
  'utf8'
);

class FakeClock {
  constructor(now = 0) {
    this.now = now;
    this.nextId = 1;
    this.tasks = new Map();
  }

  setTimeout = (callback, delay = 0) => {
    const id = this.nextId++;
    this.tasks.set(id, {
      at: this.now + Math.max(0, Number(delay) || 0),
      callback,
      interval: 0,
    });
    return id;
  };

  clearTimeout = id => {
    this.tasks.delete(id);
  };

  setInterval = (callback, delay = 0) => {
    const interval = Math.max(1, Number(delay) || 0);
    const id = this.nextId++;
    this.tasks.set(id, {
      at: this.now + interval,
      callback,
      interval,
    });
    return id;
  };

  clearInterval = id => {
    this.tasks.delete(id);
  };

  async tick(ms) {
    const target = this.now + Math.max(0, Number(ms) || 0);
    let executions = 0;

    while (true) {
      const next = Array.from(this.tasks.entries())
        .filter(([, task]) => task.at <= target)
        .sort((left, right) => left[1].at - right[1].at || left[0] - right[0])[0];
      if (!next) break;
      if (++executions > 20000) {
        throw new Error('Fake clock runaway');
      }

      const [id, task] = next;
      this.tasks.delete(id);
      this.now = task.at;
      task.callback();
      if (task.interval > 0 && !this.tasks.has(id)) {
        task.at = this.now + task.interval;
        this.tasks.set(id, task);
      }
      await Promise.resolve();
    }

    this.now = target;
    await Promise.resolve();
  }
}

class FakeEventTarget {
  constructor() {
    this.listeners = new Map();
  }

  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) || new Set();
    listeners.add(listener);
    this.listeners.set(type, listeners);
  }

  removeEventListener(type, listener) {
    this.listeners.get(type)?.delete(listener);
  }

  dispatch(type, detail = {}) {
    const event = { type, target: this, ...detail };
    Array.from(this.listeners.get(type) || []).forEach(listener => listener(event));
  }
}

class FakeClassList {
  constructor(classes = [], onChange = () => {}) {
    this.values = new Set(classes);
    this.onChange = onChange;
  }

  add(...classes) {
    let changed = false;
    classes.forEach(value => {
      if (!this.values.has(value)) {
        this.values.add(value);
        changed = true;
      }
    });
    if (changed) this.onChange();
  }

  remove(...classes) {
    let changed = false;
    classes.forEach(value => {
      if (this.values.delete(value)) changed = true;
    });
    if (changed) this.onChange();
  }

  contains(value) {
    return this.values.has(value);
  }

  toggle(value, force) {
    const enabled = force === undefined ? !this.values.has(value) : Boolean(force);
    if (enabled) this.add(value);
    else this.remove(value);
    return enabled;
  }
}

class FakeStorage {
  constructor(initial = {}) {
    this.values = new Map(Object.entries(initial));
  }

  getItem(key) {
    return this.values.has(key) ? this.values.get(key) : null;
  }

  setItem(key, value) {
    this.values.set(key, String(value));
  }

  removeItem(key) {
    this.values.delete(key);
  }
}

class FakeMediaElement extends FakeEventTarget {
  constructor(tagName, source, clock, outcomes = []) {
    super();
    this.tagName = tagName.toUpperCase();
    this.dataset = { src: source };
    this.classList = new FakeClassList();
    this.style = {};
    this.attributes = new Map();
    this.clock = clock;
    this.outcomes = Array.from(outcomes);
    this.assignments = [];
    this.complete = false;
    this.naturalWidth = 0;
    this.readyState = 0;
    this.currentTime = 0;
    this.slide = null;
    this.contentWindow = { location: { href: 'about:blank' } };
  }

  getAttribute(name) {
    return this.attributes.has(name) ? this.attributes.get(name) : null;
  }

  setAttribute(name, value) {
    const normalized = String(value);
    this.attributes.set(name, normalized);
    if (name !== 'src') return;

    this.assignments.push(normalized);
    if (normalized.startsWith('about:blank')) return;
    if (this.tagName === 'IMG') {
      this.complete = false;
      this.naturalWidth = 0;
    } else if (this.tagName === 'VIDEO') {
      this.readyState = 0;
    }
    const outcome = this.outcomes.shift() || { type: 'none' };
    if (outcome.type === 'none') return;

    this.clock.setTimeout(() => {
      if (this.getAttribute('src') !== normalized) return;
      if (this.tagName === 'IMG') {
        this.complete = true;
        this.naturalWidth = outcome.type === 'load'
          ? Number(outcome.naturalWidth || 640)
          : 0;
      } else if (this.tagName === 'VIDEO') {
        this.readyState = outcome.type === 'load'
          ? Number(outcome.readyState ?? 2)
          : 0;
      } else if (this.tagName === 'IFRAME' && outcome.type === 'load') {
        this.contentWindow.location.href = normalized;
      }
      this.dispatch(outcome.type === 'load'
        ? (this.tagName === 'VIDEO' ? 'canplay' : 'load')
        : 'error');
    }, outcome.delay || 0);
  }

  removeAttribute(name) {
    this.attributes.delete(name);
  }

  closest(selector) {
    if (selector === '.slide') return this.slide;
    return null;
  }

  matches() {
    return false;
  }

  load() {}

  pause() {}

  play() {
    return Promise.resolve();
  }
}

class FakeSlide {
  constructor(id, media, onMutation) {
    this.tagName = 'DIV';
    this.dataset = {
      slideId: String(id),
      slideType: media[0]?.tagName?.toLowerCase() || 'text',
      duration: '1',
      offlinePolicy: 'try',
    };
    this.classList = new FakeClassList(['slide'], onMutation);
    this.media = media;
    this.style = {};
    media.forEach(element => {
      element.slide = this;
    });
  }

  querySelectorAll(selector) {
    if (selector === 'img[data-src], video[data-src], iframe[data-src]') {
      return this.media;
    }
    if (selector === 'img[data-src], video[data-src]') {
      return this.media.filter(element => element.tagName === 'IMG' || element.tagName === 'VIDEO');
    }
    if (selector === 'video[data-src]' || selector === 'video') {
      return this.media.filter(element => element.tagName === 'VIDEO');
    }
    if (selector === 'iframe[data-src]') {
      return this.media.filter(element => element.tagName === 'IFRAME');
    }
    return [];
  }

  querySelector(selector) {
    if (selector === 'iframe[data-src], iframe[src]') {
      return this.media.find(element => element.tagName === 'IFRAME') || null;
    }
    return null;
  }
}

class FakeStatusScreen {
  constructor(onMutation) {
    this.message = { textContent: 'Waiting for content' };
    this.classList = new FakeClassList(['playback-status'], onMutation);
  }

  querySelector(selector) {
    return selector === 'p' ? this.message : null;
  }
}

const flushMicrotasks = async (rounds = 12) => {
  for (let index = 0; index < rounds; index += 1) {
    await Promise.resolve();
  }
};

const settle = async (clock, rounds = 8) => {
  for (let index = 0; index < rounds; index += 1) {
    await flushMicrotasks();
    await clock.tick(0);
  }
};

const createRendererRealm = slideSpecs => {
  const clock = new FakeClock();
  const rootClasses = new FakeClassList(['hugin-startup-loading-seen']);
  const violations = [];
  let monitoring = false;
  let slides = [];
  let slideshow;
  let statusScreen;
  let rendererContext = null;

  const presentationCovered = () => (
    slides.some(slide => (
      slide.classList.contains('is-active')
      && rendererContext?.__huginDisplayMedia?.isSlideReady(slide)
    ))
    || slideshow?.classList.contains('is-startup-sync-pending')
    || slideshow?.classList.contains('is-media-startup-pending')
    || statusScreen?.classList.contains('is-active')
  );
  const onMutation = () => {
    if (monitoring && !presentationCovered()) {
      violations.push({
        active: slides.filter(slide => slide.classList.contains('is-active')).length,
        startupPending: slideshow.classList.contains('is-startup-sync-pending'),
        mediaPending: slideshow.classList.contains('is-media-startup-pending'),
        status: statusScreen.classList.contains('is-active'),
      });
    }
  };

  slides = slideSpecs.map((spec, index) => {
    const media = new FakeMediaElement(
      spec.tagName || 'img',
      spec.source || `/media/${index}.png`,
      clock,
      spec.outcomes || []
    );
    return new FakeSlide(index + 1, [media], onMutation);
  });
  statusScreen = new FakeStatusScreen(onMutation);
  slideshow = {
    dataset: {
      defaultDuration: '1',
      displayPreview: '0',
      stateUrl: '',
      serviceWorkerUrl: '',
      offlineManifestUrl: '',
      cacheReadinessUrl: '',
      playbackStatus: 'ready',
      mediaUnavailableMessage: 'Content temporarily unavailable; retrying',
      stateCheckInterval: '3600',
      startupSyncKey: 'hugin-test-started',
      syncReloadToFullMinute: '0',
    },
    classList: new FakeClassList([
      'slideshow',
      'is-startup-sync-pending',
      'is-media-startup-pending',
    ], onMutation),
    querySelector(selector) {
      if (selector === '.startup-loading__status') {
        return { textContent: 'Preparing content' };
      }
      if (selector === '[data-playback-status-screen]') return statusScreen;
      return null;
    },
  };

  const documentTarget = new FakeEventTarget();
  const document = Object.assign(documentTarget, {
    visibilityState: 'visible',
    documentElement: { classList: rootClasses },
    getElementById: id => id === 'slideshow' ? slideshow : null,
    querySelectorAll: selector => selector === '.slide' ? slides : [],
    createElement: tagName => ({ tagName: tagName.toUpperCase() }),
    createTextNode: text => ({ textContent: String(text) }),
  });
  const windowTarget = new FakeEventTarget();
  const storage = new FakeStorage({ 'hugin-test-started': '1' });
  const logs = [];
  const FakeDate = class extends Date {
    constructor(...args) {
      super(...(args.length > 0 ? args : [clock.now]));
    }

    static now() {
      return clock.now;
    }
  };
  const location = {
    href: 'https://display.test/display/integration',
    origin: 'https://display.test',
    protocol: 'https:',
    host: 'display.test',
    pathname: '/display/integration',
    reload() {
      logs.push(['reload']);
    },
  };
  const context = Object.assign(windowTarget, {
    document,
    navigator: { onLine: true },
    location,
    localStorage: new FakeStorage(),
    sessionStorage: storage,
    performance: {
      getEntriesByType: () => [{ type: 'reload' }],
      navigation: { type: 1 },
    },
    console: {
      info: (...args) => logs.push(['info', ...args]),
      error: (...args) => logs.push(['error', ...args]),
    },
    Date: FakeDate,
    URL,
    Promise,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
    setInterval: clock.setInterval,
    clearInterval: clock.clearInterval,
    requestAnimationFrame: callback => clock.setTimeout(callback, 0),
    matchMedia: () => ({ matches: true }),
    getComputedStyle: () => ({ getPropertyValue: () => '0ms' }),
  });
  context.window = context;
  context.globalThis = context;
  rendererContext = context;

  monitoring = true;
  vm.runInNewContext(lifecycleSource, context, {
    filename: 'display-media-lifecycle.js',
  });
  vm.runInNewContext(slideshowSource, context, {
    filename: 'slideshow.js',
  });

  return {
    clock,
    context,
    slides,
    slideshow,
    statusScreen,
    violations,
    logs,
    activeSlides: () => slides.filter(slide => slide.classList.contains('is-active')),
  };
};

const createLifecycleRealm = clock => {
  const context = {
    console,
    Promise,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
  };
  context.window = context;
  context.globalThis = context;
  vm.runInNewContext(lifecycleSource, context, {
    filename: 'display-media-lifecycle.js',
  });
  return context.HuginDisplayMedia.create({
    host: context,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
    now: () => clock.now,
    timeoutMs: 8000,
    retryDelayMs: 30000,
  });
};

assert.match(
  displayCss,
  /\.hugin-startup-loading-seen \.slideshow\.is-media-startup-pending \.startup-loading\s*\{\s*display:\s*grid;/,
  'a reload marker must not suppress the media-readiness loader'
);
assert.match(
  displayCss,
  /\.hugin-startup-loading-seen \.slideshow\.is-media-startup-pending \.slide\s*\{\s*visibility:\s*hidden;/,
  'unverified slides must remain hidden on a reload'
);
assert.match(
  displayCss,
  /\.playback-status\s*\{[^}]*background:\s*#(?!000(?:000)?\b)[0-9a-f]{6};/s,
  'the all-content-failed status surface must be nonblack'
);

{
  const realm = createRendererRealm([
    { outcomes: [{ type: 'error' }] },
    { outcomes: [{ type: 'load', naturalWidth: 960 }] },
  ]);

  assert.equal(realm.activeSlides().length, 0);
  assert.equal(realm.slideshow.classList.contains('is-media-startup-pending'), true);
  assert.equal(
    realm.slideshow.classList.contains('is-startup-sync-pending'),
    true,
    'reload startup stays covered until a candidate is verified'
  );

  await settle(realm.clock);
  assert.deepEqual(
    realm.activeSlides().map(slide => slide.dataset.slideId),
    ['2'],
    'actual slideshow startup skips the failed first image and activates the verified second image'
  );
  assert.equal(realm.slides[0].media[0].naturalWidth, 0);
  assert.equal(realm.slideshow.classList.contains('is-media-startup-pending'), false);
  assert.equal(realm.statusScreen.classList.contains('is-active'), false);
  assert.deepEqual(realm.violations, []);
}

{
  const realm = createRendererRealm([
    {
      outcomes: [
        { type: 'error' },
        { type: 'load', naturalWidth: 1024 },
      ],
    },
    { outcomes: [{ type: 'error' }] },
  ]);

  await settle(realm.clock);
  assert.equal(realm.activeSlides().length, 0);
  assert.equal(
    realm.statusScreen.classList.contains('is-active'),
    true,
    'all failed candidates activate the existing playback-status surface'
  );
  assert.equal(
    realm.statusScreen.message.textContent,
    'Content temporarily unavailable; retrying'
  );
  assert.equal(realm.slideshow.classList.contains('is-media-startup-pending'), false);
  assert.deepEqual(realm.violations, []);

  await realm.clock.tick(30000);
  await settle(realm.clock);
  assert.deepEqual(
    realm.activeSlides().map(slide => slide.dataset.slideId),
    ['1'],
    'the scheduled explicit retry replaces status only after media verifies successfully'
  );
  assert.equal(realm.statusScreen.classList.contains('is-active'), false);
  assert.deepEqual(realm.violations, []);
}

{
  const realm = createRendererRealm([
    { outcomes: [{ type: 'load' }] },
    { outcomes: [{ type: 'load' }] },
    { outcomes: [{ type: 'load' }] },
  ]);
  await settle(realm.clock);
  assert.equal(realm.activeSlides().length, 1);

  const observed = new Set(realm.activeSlides().map(slide => slide.dataset.slideId));
  for (let cycle = 0; cycle < 12; cycle += 1) {
    await realm.clock.tick(1000);
    await settle(realm.clock);
    assert.equal(
      realm.activeSlides().length,
      1,
      `cycle ${cycle + 1} leaves exactly one verified slide visible`
    );
    observed.add(realm.activeSlides()[0].dataset.slideId);
  }
  assert.deepEqual(Array.from(observed).sort(), ['1', '2', '3']);
  assert.deepEqual(
    realm.violations,
    [],
    'actual slideshow handoffs never expose an uncovered zero-visible-slide state'
  );
}

{
  const realm = createRendererRealm([
    { outcomes: [{ type: 'load' }] },
    { outcomes: [{ type: 'none' }] },
  ]);
  await settle(realm.clock);
  assert.deepEqual(realm.activeSlides().map(slide => slide.dataset.slideId), ['1']);

  await realm.clock.tick(1000);
  await flushMicrotasks();
  const candidate = realm.slides[1].media[0];
  candidate.complete = true;
  candidate.naturalWidth = 960;
  candidate.dispatch('load');
  await flushMicrotasks();
  assert.equal(realm.context.__huginDisplayMedia.inspect(candidate).state, 'ready');

  realm.slides[1].dataset.offlinePolicy = 'skip';
  realm.context.navigator.onLine = false;
  await realm.clock.tick(0);
  await flushMicrotasks();
  assert.deepEqual(
    realm.activeSlides().map(slide => slide.dataset.slideId),
    ['1'],
    'eligibility is rechecked after readiness and before the animation-frame commit'
  );
  assert.equal(realm.statusScreen.classList.contains('is-active'), false);
  assert.deepEqual(realm.violations, []);
}

{
  const realm = createRendererRealm([
    { outcomes: [{ type: 'load' }] },
    { outcomes: [{ type: 'load' }] },
    { outcomes: [{ type: 'error' }] },
    { outcomes: [{ type: 'none' }] },
  ]);
  await settle(realm.clock);
  await realm.clock.tick(1000);
  await settle(realm.clock);
  assert.deepEqual(realm.activeSlides().map(slide => slide.dataset.slideId), ['2']);

  await realm.clock.tick(1000);
  await flushMicrotasks();
  const fartherCandidate = realm.slides[3].media[0];
  assert.equal(
    realm.context.__huginDisplayMedia.inspect(fartherCandidate).state,
    'loading',
    'transition scans beyond the failed immediate neighbor'
  );

  await realm.clock.tick(300);
  await flushMicrotasks();
  assert.equal(
    realm.context.__huginDisplayMedia.inspect(fartherCandidate).state,
    'loading',
    'deferred cleanup cannot dispose a farther candidate while transition readiness is pending'
  );
  fartherCandidate.complete = true;
  fartherCandidate.naturalWidth = 960;
  fartherCandidate.dispatch('load');
  await flushMicrotasks();
  await realm.clock.tick(0);
  await settle(realm.clock);
  assert.deepEqual(realm.activeSlides().map(slide => slide.dataset.slideId), ['4']);
  assert.deepEqual(realm.violations, []);
}

{
  const firstRealm = createRendererRealm([
    { outcomes: [{ type: 'load' }] },
  ]);
  await settle(firstRealm.clock);
  const secondRealm = createRendererRealm([
    { outcomes: [{ type: 'load' }] },
  ]);
  await settle(secondRealm.clock);

  assert.equal(firstRealm.activeSlides().length, 1);
  assert.equal(secondRealm.activeSlides().length, 1);
  assert.notEqual(
    firstRealm.context.__huginDisplayMedia,
    secondRealm.context.__huginDisplayMedia,
    'a full document reload initializes a fresh media owner in a fresh VM realm'
  );
  assert.deepEqual(firstRealm.violations, []);
  assert.deepEqual(secondRealm.violations, []);
}
{
  const realm = createRendererRealm([
    {
      tagName: 'iframe',
      source: 'http://online-only.internal/dashboard',
      outcomes: [{ type: 'load' }],
    },
  ]);
  await settle(realm.clock);
  assert.equal(realm.activeSlides().length, 1);

  const frame = realm.slides[0].media[0];
  realm.context.navigator.onLine = false;
  realm.context.dispatch('offline');
  await settle(realm.clock);

  assert.equal(realm.activeSlides().length, 0, 'an offline-only iframe cannot remain as an active black surface');
  assert.equal(realm.statusScreen.classList.contains('is-active'), true, 'no eligible offline candidate activates the nonblack status surface');
  assert.equal(realm.context.__huginDisplayMedia.inspect(frame).state, 'disposed');
  assert.match(frame.getAttribute('src'), /^about:blank\?hugin-media-reset=/);
  assert.deepEqual(realm.violations, [], 'status is visible before the online-only frame is removed');
}

{
  const clock = new FakeClock();
  const lifecycle = createLifecycleRealm(clock);
  const video = new FakeMediaElement('video', '/media/stale.mp4', clock);
  const readiness = lifecycle.prepare(video);
  video.readyState = 0;
  video.dispatch('canplay');
  assert.equal(
    lifecycle.inspect(video).state,
    'loading',
    'a stale canplay event with no current video data cannot establish readiness'
  );
  await clock.tick(8000);
  assert.equal(await readiness, false);
  assert.equal(lifecycle.inspect(video).failureReason, 'timeout');
}

{
  const clock = new FakeClock();
  const lifecycle = createLifecycleRealm(clock);
  const frame = new FakeMediaElement(
    'iframe',
    'http://policy-allowed.internal/dashboard',
    clock
  );
  const first = lifecycle.prepare(frame);
  frame.dispatch('error');
  assert.equal(await first, false);
  await clock.tick(30000);

  const retry = lifecycle.retry(frame);
  const blankUrl = frame.getAttribute('src');
  assert.match(blankUrl, /^about:blank\?hugin-media-reset=/);
  frame.contentWindow.location.href = frame.dataset.src;
  frame.dispatch('load');
  assert.equal(
    frame.getAttribute('src'),
    blankUrl,
    'an old-target load cannot complete the blank reset'
  );

  frame.contentWindow.location.href = blankUrl;
  frame.dispatch('load');
  await flushMicrotasks();
  assert.equal(frame.getAttribute('src'), frame.dataset.src);
  assert.equal(lifecycle.inspect(frame).state, 'loading');

  frame.contentWindow.location.href = frame.dataset.src;
  frame.dispatch('load');
  assert.equal(await retry, true);
  assert.equal(lifecycle.inspect(frame).state, 'ready');
}

{
  const clock = new FakeClock();
  const lifecycle = createLifecycleRealm(clock);
  const frame = new FakeMediaElement(
    'iframe',
    'http://eventually-available.internal/dashboard',
    clock
  );
  const initial = lifecycle.prepare(frame);
  frame.dispatch('error');
  assert.equal(await initial, false);
  await clock.tick(30000);

  const missingBlank = lifecycle.retry(frame);
  assert.match(frame.getAttribute('src'), /^about:blank\?hugin-media-reset=/);
  await clock.tick(8000);
  assert.equal(await missingBlank, false, 'a missing blank load is bounded by the attempt timeout');
  await clock.tick(30000);

  const nextRetry = lifecycle.retry(frame);
  const nextBlankUrl = frame.getAttribute('src');
  frame.contentWindow.location.href = nextBlankUrl;
  frame.dispatch('load');
  await flushMicrotasks();
  assert.equal(frame.getAttribute('src'), frame.dataset.src);
  frame.contentWindow.location.href = frame.dataset.src;
  frame.dispatch('load');
  assert.equal(
    await nextRetry,
    true,
    'one missing about:blank event does not wedge a later explicit iframe retry'
  );
}

console.log('display renderer integration regression tests passed');
