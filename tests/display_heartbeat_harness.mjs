export const flushPromises = async () => {
    await new Promise(resolve => setImmediate(resolve));
    await new Promise(resolve => setImmediate(resolve));
};

class EventTargetHarness {
    constructor() {
        this.listeners = new Map();
    }

    addEventListener(name, handler) {
        if (!this.listeners.has(name)) this.listeners.set(name, new Set());
        this.listeners.get(name).add(handler);
    }

    removeEventListener(name, handler) {
        this.listeners.get(name)?.delete(handler);
    }

    emit(name, event = {}) {
        for (const handler of Array.from(this.listeners.get(name) || [])) handler(event);
    }

    count(name) {
        return this.listeners.get(name)?.size || 0;
    }

    total() {
        return Array.from(this.listeners.values()).reduce((sum, handlers) => sum + handlers.size, 0);
    }
}

export class FakeClock {
    constructor(now = 1_000_000) {
        this.now = now;
        this.nextId = 1;
        this.timers = new Map();
    }

    setTimeout(handler, delay = 0) {
        return this.add(handler, delay, 0);
    }

    setInterval(handler, delay = 0) {
        const normalizedDelay = Math.max(1, Number(delay) || 0);
        return this.add(handler, normalizedDelay, normalizedDelay);
    }

    add(handler, delay, interval) {
        const id = this.nextId++;
        this.timers.set(id, {
            handler,
            due: this.now + Math.max(0, Number(delay) || 0),
            interval,
        });
        return id;
    }

    clear(id) {
        this.timers.delete(id);
    }

    intervalCount() {
        return Array.from(this.timers.values()).filter(timer => timer.interval > 0).length;
    }

    timeoutCount() {
        return Array.from(this.timers.values()).filter(timer => timer.interval === 0).length;
    }

    async advance(milliseconds) {
        const target = this.now + milliseconds;
        while (true) {
            const next = Array.from(this.timers.entries())
                .filter(([, timer]) => timer.due <= target)
                .sort((left, right) => left[1].due - right[1].due || left[0] - right[0])[0];
            if (!next) break;

            const [id, timer] = next;
            this.now = timer.due;
            if (timer.interval > 0) {
                timer.due += timer.interval;
            } else {
                this.timers.delete(id);
            }
            timer.handler();
            await flushPromises();
        }
        this.now = target;
        await flushPromises();
    }
}

export const createDeferredResponse = () => {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });
    return { promise, resolve, reject };
};

export function installHeartbeatHarness({ intervalSeconds = 60, initialFetchHandler } = {}) {
    const clock = new FakeClock();
    const windowEvents = new EventTargetHarness();
    const documentEvents = new EventTargetHarness();
    const orientationEvents = new EventTargetHarness();
    const requests = [];
    const beacons = [];
    const warnings = [];
    const infos = [];
    let fetchHandler = initialFetchHandler || (() => Promise.resolve({ ok: true, status: 200 }));
    let activeFetches = 0;
    let maxActiveFetches = 0;

    globalThis.window = globalThis;
    window.location = {
        href: 'https://hugin.test/display/lobby',
        origin: 'https://hugin.test',
        protocol: 'https:',
        host: 'hugin.test',
    };
    window.innerWidth = 1920;
    window.innerHeight = 1080;
    window.devicePixelRatio = 1;
    window.setTimeout = clock.setTimeout.bind(clock);
    window.clearTimeout = clock.clear.bind(clock);
    window.setInterval = clock.setInterval.bind(clock);
    window.clearInterval = clock.clear.bind(clock);
    window.addEventListener = windowEvents.addEventListener.bind(windowEvents);
    window.removeEventListener = windowEvents.removeEventListener.bind(windowEvents);
    window.console = {
        ...console,
        warn: (...args) => warnings.push(args),
        info: (...args) => infos.push(args),
    };
    Date.now = () => clock.now;

    const slideshow = {
        dataset: {
            heartbeatUrl: '/display/lobby/heartbeat',
            heartbeatInterval: String(intervalSeconds),
        },
    };
    globalThis.document = {
        documentElement: { clientWidth: 1920, clientHeight: 1080 },
        visibilityState: 'visible',
        getElementById: id => id === 'slideshow' ? slideshow : null,
        addEventListener: documentEvents.addEventListener.bind(documentEvents),
        removeEventListener: documentEvents.removeEventListener.bind(documentEvents),
    };
    globalThis.screen = {
        width: 1920,
        height: 1080,
        availWidth: 1920,
        availHeight: 1040,
        colorDepth: 24,
        orientation: {
            type: 'landscape-primary',
            addEventListener: orientationEvents.addEventListener.bind(orientationEvents),
            removeEventListener: orientationEvents.removeEventListener.bind(orientationEvents),
        },
    };

    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: {
            userAgent: 'Mozilla/5.0 Chrome/130.0.0.0',
            platform: 'Linux x86_64',
            language: 'en-US',
            maxTouchPoints: 0,
            hardwareConcurrency: 4,
            deviceMemory: 4,
            onLine: true,
            cookieEnabled: true,
            sendBeacon: (url, body) => {
                beacons.push({ url, body });
                return true;
            },
        },
    });

    if (typeof globalThis.Blob !== 'function') {
        globalThis.Blob = class Blob {
            constructor(parts, options) {
                this.parts = parts;
                this.type = options?.type || '';
            }
        };
    }

    window.fetch = (url, options) => {
        const request = { url, options, payload: JSON.parse(options.body) };
        requests.push(request);
        activeFetches += 1;
        maxActiveFetches = Math.max(maxActiveFetches, activeFetches);
        let result;
        try {
            result = fetchHandler(request);
        } catch (error) {
            activeFetches -= 1;
            throw error;
        }
        return Promise.resolve(result).finally(() => {
            activeFetches -= 1;
        });
    };

    return {
        clock,
        slideshow,
        requests,
        beacons,
        warnings,
        infos,
        setFetchHandler: handler => { fetchHandler = handler; },
        maxActiveFetches: () => maxActiveFetches,
        activeFetches: () => activeFetches,
        emitWindow: (name, event = {}) => windowEvents.emit(name, event),
        emitDocument: (name, event = {}) => documentEvents.emit(name, event),
        emitOrientation: (name, event = {}) => orientationEvents.emit(name, event),
        listenerCount: (target, name) => {
            if (target === 'window') return windowEvents.count(name);
            if (target === 'document') return documentEvents.count(name);
            return orientationEvents.count(name);
        },
        totalListeners: () => windowEvents.total() + documentEvents.total() + orientationEvents.total(),
    };
}

export const pendingUntilAbort = request => new Promise((resolve, reject) => {
    if (!request.options.signal) return;
    request.options.signal.addEventListener('abort', () => reject(new Error('request aborted')), { once: true });
});

export const assert = (condition, message) => {
    if (!condition) throw new Error(message);
};
