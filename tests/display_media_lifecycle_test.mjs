import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const lifecycleSource = fs.readFileSync(new URL('../public/assets/js/display-media-lifecycle.js', import.meta.url), 'utf8');
const slideshowSource = fs.readFileSync(new URL('../public/assets/js/slideshow.js', import.meta.url), 'utf8');
const displayView = fs.readFileSync(new URL('../app/Views/frontend/display.php', import.meta.url), 'utf8');
const controllerSource = fs.readFileSync(new URL('../app/Controllers/FrontendController.php', import.meta.url), 'utf8');
const serviceWorkerSource = fs.readFileSync(new URL('../public/display-service-worker.js', import.meta.url), 'utf8');
const displayCss = fs.readFileSync(new URL('../public/assets/css/display.css', import.meta.url), 'utf8');

class FakeClock {
  constructor() {
    this.now = 0;
    this.nextId = 1;
    this.tasks = new Map();
  }

  setTimeout = (callback, delay = 0) => {
    const id = this.nextId++;
    this.tasks.set(id, { at: this.now + Math.max(0, Number(delay) || 0), callback });
    return id;
  };

  clearTimeout = id => {
    this.tasks.delete(id);
  };

  async tick(ms) {
    const target = this.now + ms;
    while (true) {
      const next = Array.from(this.tasks.entries())
        .filter(([, task]) => task.at <= target)
        .sort((left, right) => left[1].at - right[1].at || left[0] - right[0])[0];
      if (!next) break;
      this.tasks.delete(next[0]);
      this.now = next[1].at;
      next[1].callback();
      await Promise.resolve();
    }
    this.now = target;
    await Promise.resolve();
  }
}

class FakeClassList {
  constructor(classes = []) {
    this.values = new Set(classes);
  }

  add(...classes) {
    classes.forEach(value => this.values.add(value));
  }

  remove(...classes) {
    classes.forEach(value => this.values.delete(value));
  }

  contains(value) {
    return this.values.has(value);
  }
}

class FakeElement {
  constructor(tagName, source, classes = []) {
    this.tagName = tagName.toUpperCase();
    this.dataset = { src: source };
    this.classList = new FakeClassList(classes);
    this.attributes = new Map();
    this.listeners = new Map();
    this.complete = false;
    this.naturalWidth = 0;
    this.readyState = 0;
    this.srcAssignments = 0;
    this.loadCalls = 0;
    this.decode = undefined;
    this.slide = null;
  }

  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) || new Set();
    listeners.add(listener);
    this.listeners.set(type, listeners);
  }

  removeEventListener(type, listener) {
    this.listeners.get(type)?.delete(listener);
  }

  dispatch(type) {
    Array.from(this.listeners.get(type) || []).forEach(listener => listener({ type, target: this }));
  }

  getAttribute(name) {
    return this.attributes.has(name) ? this.attributes.get(name) : null;
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name === 'src') this.srcAssignments += 1;
  }

  removeAttribute(name) {
    this.attributes.delete(name);
  }

  load() {
    this.loadCalls += 1;
  }

  closest(selector) {
    return selector === '.slide' ? this.slide : null;
  }
}

const fakeSlide = (elements, classes = []) => {
  const slide = {
    classList: new FakeClassList(classes),
    dataset: {},
    querySelectorAll: selector => (
      selector === 'img[data-src], video[data-src], iframe[data-src]' ? elements : []
    ),
  };
  elements.forEach(element => {
    element.slide = slide;
  });
  return slide;
};

const loadLifecycle = clock => {
  const context = {
    console,
    Promise,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
  };
  context.window = context;
  vm.runInNewContext(lifecycleSource, context, { filename: 'display-media-lifecycle.js' });
  return context.HuginDisplayMedia;
};

const managerFor = (clock, options = {}) => loadLifecycle(clock).create({
  host: {
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clearTimeout,
  },
  setTimeout: clock.setTimeout,
  clearTimeout: clock.clearTimeout,
  now: () => clock.now,
  timeoutMs: 8000,
  retryDelayMs: 30000,
  ...options,
});

const flushMicrotasks = async () => {
  for (let index = 0; index < 20; index += 1) {
    await Promise.resolve();
  }
};

{
  const clock = new FakeClock();
  const failures = [];
  const manager = managerFor(clock, { onFailure: (_element, detail) => failures.push(detail) });
  const image = new FakeElement('img', '/uploads/first.png');
  image.setAttribute('src', '/uploads/first.png');
  image.complete = true;
  image.naturalWidth = 0;

  assert.equal(await manager.prepare(image), false, 'an eagerly failed image must not be accepted as ready');
  assert.equal(manager.inspect(image).state, 'failed');
  assert.equal(manager.inspect(image).failureReason, 'empty-image');
  assert.equal(image.dataset.mediaFailedAt, '0', 'failure time remains explicit even at a zero fake epoch');
  assert.equal(failures.length, 1);

  await clock.tick(29999);
  const blockedRetryAttempt = manager.inspect(image).attempt;
  assert.equal(await manager.retry(image), false, 'elapsed failure time only controls retry eligibility');
  assert.equal(manager.inspect(image).attempt, blockedRetryAttempt);
  assert.equal(manager.isSlideReady(fakeSlide([image])), false, 'a failed image never ages into ready');

  await clock.tick(1);
  image.complete = false;
  const retry = manager.retry(image);
  assert.equal(manager.inspect(image).state, 'loading');
  image.complete = true;
  image.naturalWidth = 640;
  image.decode = () => Promise.resolve();
  image.dispatch('load');
  assert.equal(await retry, true);
  assert.equal(manager.inspect(image).state, 'ready');
  assert.equal(image.classList.contains('is-media-error'), false);
  assert.equal('mediaFailedAt' in image.dataset, false);
  assert.equal(image.srcAssignments, 2, 'the explicit retry starts exactly one new request');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const image = new FakeElement('img', '/uploads/slow.png');
  const first = manager.prepare(image);
  const shared = manager.prepare(image);
  assert.equal(first, shared, 'concurrent callers share one loading attempt');
  assert.equal(image.srcAssignments, 1);
  await clock.tick(7999);
  assert.equal(manager.inspect(image).state, 'loading', 'a slow asset remains eligible to finish before the bounded deadline');
  image.complete = true;
  image.naturalWidth = 320;
  image.decode = () => Promise.resolve();
  image.dispatch('load');
  assert.deepEqual(await Promise.all([first, shared]), [true, true]);
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const image = new FakeElement('img', '/uploads/decode-never.png');
  image.decode = () => new Promise(() => {});
  const readiness = manager.prepare(image);
  image.complete = true;
  image.naturalWidth = 320;
  image.dispatch('load');
  await clock.tick(7999);
  assert.equal(manager.inspect(image).state, 'loading');
  await clock.tick(1);
  assert.equal(await readiness, false, 'a never-settling decode is bounded by the attempt timeout');
  assert.equal(manager.inspect(image).failureReason, 'timeout');
}

{
  const clock = new FakeClock();
  const failures = [];
  const manager = managerFor(clock, { onFailure: (_element, detail) => failures.push(detail) });
  const image = new FakeElement('img', '/uploads/late-decode.png');
  let rejectDecode;
  image.decode = () => new Promise((_resolve, reject) => { rejectDecode = reject; });
  const readiness = manager.prepare(image);
  image.complete = true;
  image.naturalWidth = 320;
  image.dispatch('load');
  await clock.tick(8000);
  assert.equal(await readiness, false);
  const failedAt = manager.inspect(image).failedAt;
  const retryAt = manager.inspect(image).retryAt;
  assert.equal(failures.length, 1);

  await clock.tick(5000);
  rejectDecode(new Error('late decode rejection'));
  await flushMicrotasks();
  assert.equal(manager.inspect(image).state, 'failed', 'late decode rejection stays stale after timeout');
  assert.equal(manager.inspect(image).failedAt, failedAt, 'stale decode cannot move the failure timestamp');
  assert.equal(manager.inspect(image).retryAt, retryAt, 'stale decode cannot postpone retry eligibility');
  assert.equal(failures.length, 1, 'stale decode cannot emit a duplicate failure');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const allowedHttpFrame = new FakeElement('iframe', 'http://policy-allowed.internal/dashboard');
  const allowed = manager.prepare(allowedHttpFrame);
  assert.equal(allowedHttpFrame.getAttribute('loading'), 'eager');
  allowedHttpFrame.dispatch('load');
  assert.equal(await allowed, true, 'HTTP iframe policy is decided by the browser, not blanket-rejected');

  manager.dispose(allowedHttpFrame, { unload: true });
  allowedHttpFrame.dispatch('load');
  assert.equal(manager.inspect(allowedHttpFrame).state, 'disposed', 'about:blank unload events are stale');

  const blockedFrame = new FakeElement('iframe', 'http://blocked.internal/dashboard');
  const blocked = manager.prepare(blockedFrame);
  await clock.tick(8000);
  assert.equal(await blocked, false, 'a frame with neither load nor error is failed by timeout');
  assert.equal(manager.inspect(blockedFrame).failureReason, 'timeout');

  await clock.tick(30000);
  const retry = manager.retry(blockedFrame);
  const resetUrl = blockedFrame.getAttribute('src');
  assert.match(resetUrl, /^about:blank\?hugin-media-reset=\d+$/, 'iframe reset must be a load-emitting navigation, not a fragment change');
  blockedFrame.contentWindow = { location: { href: 'http://blocked.internal/dashboard' } };
  blockedFrame.dispatch('load');
  assert.equal(
    blockedFrame.getAttribute('src'),
    resetUrl,
    'a queued target load from the previous attempt cannot satisfy the reset barrier'
  );
  blockedFrame.contentWindow.location.href = resetUrl;
  blockedFrame.dispatch('load');
  await flushMicrotasks();
  assert.equal(manager.inspect(blockedFrame).state, 'loading', 'the blank reset load cannot complete the target attempt');
  assert.equal(blockedFrame.getAttribute('src'), 'http://blocked.internal/dashboard');
  blockedFrame.dispatch('load');
  assert.equal(manager.inspect(blockedFrame).state, 'loading', 'a delayed blank document load cannot complete the target attempt');
  blockedFrame.contentWindow = null;
  blockedFrame.dispatch('load');
  assert.equal(await retry, true, 'only the target navigation load completes the iframe retry');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const frame = new FakeElement('iframe', 'https://reports.example.invalid/board');
  const first = manager.prepare(frame);
  await clock.tick(8000);
  assert.equal(await first, false);

  await clock.tick(30000);
  const firstRetry = manager.retry(frame);
  const firstResetUrl = frame.getAttribute('src');
  await clock.tick(8000);
  assert.equal(await firstRetry, false, 'a reset navigation that never loads is bounded by the attempt timeout');
  assert.equal(frame.listeners.get('load')?.size || 0, 0, 'a timed-out reset attempt releases its listener immediately');

  await clock.tick(30000);
  const secondRetry = manager.retry(frame);
  const secondResetUrl = frame.getAttribute('src');
  assert.notEqual(secondResetUrl, firstResetUrl, 'each iframe retry has a distinct reset navigation identity');
  frame.contentWindow = { location: { href: secondResetUrl } };
  frame.dispatch('load');
  await flushMicrotasks();
  assert.equal(frame.getAttribute('src'), frame.dataset.src);
  frame.contentWindow = null;
  frame.dispatch('load');
  assert.equal(await secondRetry, true, 'a later retry can recover after a lost reset event');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const video = new FakeElement('video', '/uploads/stale-canplay.mp4');
  const first = manager.prepare(video);
  await clock.tick(8000);
  assert.equal(await first, false);

  await clock.tick(30000);
  const retry = manager.retry(video);
  video.readyState = 0;
  video.dispatch('canplay');
  assert.equal(
    manager.inspect(video).state,
    'loading',
    'a queued canplay event cannot mark a new attempt ready without current media data'
  );
  video.readyState = 2;
  video.dispatch('canplay');
  assert.equal(await retry, true);
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const frame = new FakeElement('iframe', 'https://destroy.example.invalid/board');
  const first = manager.prepare(frame);
  await clock.tick(8000);
  assert.equal(await first, false);
  await clock.tick(30000);

  const retry = manager.retry(frame);
  const resetUrl = frame.getAttribute('src');
  assert.equal(frame.listeners.get('load')?.size, 1);
  manager.destroy();
  assert.equal(await retry, false);
  assert.equal(frame.listeners.get('load')?.size || 0, 0, 'destroy removes an in-progress iframe reset listener');
  frame.contentWindow = { location: { href: resetUrl } };
  frame.dispatch('load');
  await flushMicrotasks();
  assert.equal(frame.getAttribute('src'), resetUrl, 'destroyed reset work cannot start a later target navigation');
  assert.equal(manager.inspect(frame).state, 'disposed');
}

{
  const clock = new FakeClock();
  const failures = [];
  const manager = managerFor(clock, { onFailure: element => failures.push(element) });
  const video = new FakeElement('video', '/uploads/clip.mp4');
  video.readyState = 2;
  assert.equal(await manager.prepare(video), true);
  video.dispatch('error');
  assert.equal(manager.inspect(video).state, 'failed', 'errors after readiness invalidate active media');
  assert.deepEqual(failures, [video]);

  const background = new FakeElement('video', '/uploads/decorative.mp4', ['text-slide-background']);
  const required = manager.requiredElements(fakeSlide([video, background]));
  assert.equal(required.length, 1, 'decorative backgrounds are optional');
  assert.equal(required[0], video);
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const video = new FakeElement('video', '/uploads/startup-failure.mp4');
  const failed = manager.prepare(video);
  video.dispatch('error');
  assert.equal(await failed, false);
  const attempt = manager.inspect(video).attempt;
  await clock.tick(40000);
  assert.equal(manager.inspect(video).state, 'failed', 'elapsed quarantine time never turns a failed video into ready media');
  assert.equal(manager.inspect(video).attempt, attempt, 'time alone does not start an implicit media attempt');
  assert.equal(manager.isSlideReady(fakeSlide([video])), false);
  assert.equal(await manager.prepare(video), false, 'selection cannot reactivate a failed video without an explicit retry');
}

{
  const clock = new FakeClock();
  const element = new FakeElement('video', '/uploads/reinit.mp4');
  element.readyState = 2;
  const firstManager = managerFor(clock);
  assert.equal(await firstManager.prepare(element), true);
  firstManager.destroy();
  assert.equal(firstManager.inspect(element).state, 'disposed');

  const secondManager = managerFor(clock);
  element.readyState = 2;
  assert.equal(await secondManager.prepare(element), true, 'a fresh document/runtime can reinitialize media ownership');
  assert.equal(secondManager.inspect(element).state, 'ready');
  element.setAttribute('src', '/uploads/external-mutation.mp4');
  assert.equal(secondManager.isSlideReady(fakeSlide([element])), false, 'external source mutation invalidates recorded readiness');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const failedImage = new FakeElement('img', '/uploads/failed-first.png');
  const failedSlide = fakeSlide([failedImage]);
  const validSlide = fakeSlide([]);
  const selection = manager.findReadyCandidate([failedSlide, validSlide], 0);
  await Promise.resolve();
  failedImage.dispatch('error');
  assert.equal(await selection, 1, 'startup selection skips a failed first slide and verifies the next');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const firstImage = new FakeElement('img', '/uploads/recover-first.png');
  const secondImage = new FakeElement('img', '/uploads/recover-second.png');
  const candidates = [fakeSlide([firstImage]), fakeSlide([secondImage])];
  const allFailed = manager.findReadyCandidate(candidates, 0);
  await Promise.resolve();
  firstImage.dispatch('error');
  await flushMicrotasks();
  secondImage.dispatch('error');
  assert.equal(await allFailed, -1, 'all failed candidates produce no active selection');

  await clock.tick(30000);
  firstImage.complete = false;
  const recovery = manager.findReadyCandidate(candidates, 0, { retryFailed: true });
  await Promise.resolve();
  firstImage.complete = true;
  firstImage.naturalWidth = 800;
  firstImage.decode = () => Promise.resolve();
  firstImage.dispatch('load');
  assert.equal(await recovery, 0, 'an explicit later scan can recover a failed candidate');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const images = Array.from({ length: 4 }, (_value, index) => (
    new FakeElement('img', '/uploads/cancel-' + index + '.png')
  ));
  let continueScan = true;
  const selection = manager.findReadyCandidate(
    images.map(image => fakeSlide([image])),
    0,
    { shouldContinue: () => continueScan }
  );
  await flushMicrotasks();
  assert.equal(manager.inspect(images[0]).state, 'loading');
  await clock.tick(8000);
  await flushMicrotasks();
  assert.equal(manager.inspect(images[0]).state, 'failed');
  assert.equal(manager.inspect(images[1]).state, 'loading');

  continueScan = false;
  await clock.tick(8000);
  await flushMicrotasks();
  assert.equal(await selection, -1, 'a canceled candidate scan returns no stale selection');
  assert.equal(manager.inspect(images[2]).state, 'idle', 'canceled startup scan cannot start a later candidate');
  assert.equal(manager.inspect(images[3]).state, 'idle', 'canceled startup scan remains bounded');
}

{
  const clock = new FakeClock();
  const manager = managerFor(clock);
  const slides = [fakeSlide([], ['is-active']), fakeSlide([]), fakeSlide([])];
  let current = 0;
  for (let cycle = 0; cycle < 60; cycle += 1) {
    const next = (current + 1) % slides.length;
    assert.equal(manager.handoffVisible(slides[current], slides[next], {
      afterAdd: () => {
        assert.ok(slides.filter(slide => slide.classList.contains('is-active')).length >= 1);
      },
      afterRemove: () => {
        assert.equal(slides.filter(slide => slide.classList.contains('is-active')).length, 1);
      },
    }), true);
    current = next;
  }

  const rollbackTarget = (current + 1) % slides.length;
  assert.throws(() => manager.handoffVisible(slides[current], slides[rollbackTarget], {
    beforeRemove: () => {
      throw new Error('synthetic commit failure');
    },
  }), /synthetic commit failure/);
  assert.equal(slides[current].classList.contains('is-active'), true);
  assert.equal(slides[rollbackTarget].classList.contains('is-active'), false);
  assert.equal(slides.filter(slide => slide.classList.contains('is-active')).length, 1);
}

assert.match(
  displayView,
  /<img data-src="<\?= e\(url\(\$slide\['resolved_source_url'\]\)\) \?>"[^>]*>/,
  'image slides must be data-src-only so handlers bind before loading'
);
assert.doesNotMatch(displayView, /fetchpriority="high"/, 'the eager first-image regression must stay removed');
assert.ok(
  displayView.indexOf('display-media-lifecycle.js') < displayView.indexOf('slideshow.js'),
  'the lifecycle helper must load before slideshow.js'
);
assert.match(displayView, /data-media-unavailable-message=/);
assert.match(displayView, /<iframe data-src=.*loading="eager"/);
for (const publicPath of [
  '/assets/css/display.css',
  '/assets/js/hugin-qr.js',
  '/assets/js/playback-scheduler.js',
  '/assets/js/display-heartbeat.js',
  '/assets/js/display-media-lifecycle.js',
  '/assets/js/slideshow.js',
  '/display-service-worker.js',
]) {
  assert.ok(displayView.includes(`$coreFrontendAssetUrl('${publicPath}')`), `${publicPath} must use the content-addressed runtime URL`);
  assert.equal(displayView.includes(`asset_url('${publicPath}')`), false, `${publicPath} must not use metadata-only versioning`);
}
assert.match(
  displayCss,
  /\.hugin-startup-loading-seen \.slideshow\.is-media-startup-pending \.startup-loading\s*\{\s*display:\s*grid;/,
  'a reload marker cannot suppress media-readiness startup coverage'
);
assert.match(
  displayCss,
  /\.hugin-startup-loading-seen \.slideshow\.is-media-startup-pending \.slide\s*\{\s*visibility:\s*hidden;/
);

assert.ok(
  lifecycleSource.indexOf('next.classList.add(activeClass)') < lifecycleSource.indexOf('current.classList.remove(activeClass)'),
  'the handoff primitive adds a verified candidate before removing the current slide'
);
assert.match(slideshowSource, /showMediaUnavailable\(\);[\s\S]*recoverFromMediaFailure\(\);/);
assert.match(slideshowSource, /Promise\.race\(\[selection, deadline\]\)/);
assert.doesNotMatch(slideshowSource, /Date\.now\(\) - failedAt < MEDIA_FAILURE_RETRY_MS/);

assert.match(controllerSource, /'core_frontend_revision' => \$this->coreFrontendRuntimeRevision\(\)/);
assert.match(controllerSource, /unset\(\$signaturePayload\['frontend_assets'\]\)/);
assert.match(controllerSource, /display-media-lifecycle\.js/);
assert.match(controllerSource, /coreFrontendAssetUrl\('\/display-service-worker\.js'\)/);
assert.match(serviceWorkerSource, /display-media-lifecycle/);

console.log('display media lifecycle regression tests passed');
