const listeners = new Map();
const scheduled = new Map();
let nextTimer = 1;
let beaconCount = 0;
let fetchMode = 'pending';
const slideshow = { dataset: { heartbeatUrl: '/display/lobby/heartbeat', heartbeatInterval: '30' } };

globalThis.window = globalThis;
window.location = { href: 'https://hugin.test/display/lobby', origin: 'https://hugin.test', protocol: 'https:', host: 'hugin.test' };
window.innerWidth = 1920;
window.innerHeight = 1080;
window.addEventListener = (name, handler) => listeners.set(`window:${name}`, handler);
window.setTimeout = (handler, delay) => {
    const id = nextTimer++;
    scheduled.set(id, { handler, delay });
    return id;
};
window.clearTimeout = id => scheduled.delete(id);
window.setInterval = (handler, delay) => {
    listeners.set('watchdog', handler);
    return nextTimer++;
};
globalThis.AbortController = undefined;
globalThis.document = {
    documentElement: { clientWidth: 1920, clientHeight: 1080 },
    visibilityState: 'hidden',
    getElementById: id => id === 'slideshow' ? slideshow : null,
    addEventListener: (name, handler) => listeners.set(`document:${name}`, handler),
};
globalThis.screen = {
    width: 1920, height: 1080, availWidth: 1920, availHeight: 1040, colorDepth: 24,
    orientation: { type: 'landscape-primary', addEventListener: () => {} },
};
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {
    userAgent: 'Embedded Chromium', platform: 'Linux', language: 'en-US', onLine: true,
    sendBeacon: () => { beaconCount += 1; return true; },
} });
globalThis.fetch = () => {
    if (fetchMode === 'throw') throw new Error('synchronous fetch failure');
    return new Promise(() => {});
};

await import('../public/assets/js/display-heartbeat.js?failure-test');
const assert = (condition, message) => { if (!condition) throw new Error(message); };
assert(window.__huginHeartbeatStatus.requestInFlight === true, 'initial pending request is tracked');

const requestTimeout = Array.from(scheduled.values()).find(item => item.delay === 15000);
assert(requestTimeout, 'a real timeout is installed without AbortController');
requestTimeout.handler();
await new Promise(resolve => setImmediate(resolve));
assert(window.__huginHeartbeatStatus.requestInFlight === false, 'timeout releases the in-flight latch');
assert(window.__huginHeartbeatStatus.consecutiveFailures === 1, 'timeout records a failure');
assert(window.__huginHeartbeatStatus.lastError.includes('timed out'), 'timeout diagnostic is exposed');
assert(beaconCount === 1, 'timeout attempts a beacon fallback');
assert(Array.from(scheduled.values()).some(item => item.delay === 5000), 'timeout schedules a retry');

fetchMode = 'throw';
listeners.get('window:online')();
await new Promise(resolve => setImmediate(resolve));
assert(window.__huginHeartbeatStatus.requestInFlight === false, 'synchronous fetch exception releases the latch');
assert(window.__huginHeartbeatStatus.consecutiveFailures === 2, 'synchronous exception records another failure');
assert(window.__huginHeartbeatStatus.lastError.includes('synchronous fetch failure'), 'exception diagnostic is exposed');

console.log('PASS heartbeat timeout and synchronous failure recovery');
