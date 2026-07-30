const listeners = new Map();
const requests = [];
const scheduled = [];
const slideshow = { dataset: { heartbeatUrl: '/display/lobby/heartbeat', heartbeatInterval: '60' } };

globalThis.window = globalThis;
window.location = { href: 'https://hugin.test/display/lobby', origin: 'https://hugin.test', protocol: 'https:', host: 'hugin.test' };
window.innerWidth = 1920;
window.innerHeight = 1080;
window.addEventListener = (name, handler) => listeners.set(`window:${name}`, handler);
window.setTimeout = (handler, delay) => { scheduled.push({ handler, delay }); return scheduled.length; };
window.clearTimeout = () => {};
window.setInterval = () => 1;
globalThis.document = {
    documentElement: { clientWidth: 1920, clientHeight: 1080 },
    visibilityState: 'visible',
    getElementById: id => id === 'slideshow' ? slideshow : null,
    addEventListener: (name, handler) => listeners.set(`document:${name}`, handler),
};
globalThis.screen = {
    width: 1920, height: 1080, availWidth: 1920, availHeight: 1040, colorDepth: 24,
    orientation: { type: 'landscape-primary', addEventListener: (name, handler) => listeners.set(`orientation:${name}`, handler) },
};
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {
    userAgent: 'Mozilla/5.0 Chrome/130.0.0.0', platform: 'Linux x86_64', language: 'en-US',
    maxTouchPoints: 0, hardwareConcurrency: 4, deviceMemory: 4, onLine: true, cookieEnabled: true,
    sendBeacon: () => true,
} });
globalThis.fetch = async (url, options) => {
    requests.push({ url, options, payload: JSON.parse(options.body) });
    return { ok: true, status: 200 };
};

await import('../public/assets/js/display-heartbeat.js');
await new Promise(resolve => setImmediate(resolve));

const assert = (condition, message) => { if (!condition) throw new Error(message); };
assert(requests.length === 1, 'heartbeat starts independently on module load');
assert(requests[0].url === 'https://hugin.test/display/lobby/heartbeat', 'heartbeat URL is unchanged');
assert(requests[0].options.method === 'POST', 'heartbeat method is POST');
assert(requests[0].options.headers['Content-Type'] === 'application/json', 'heartbeat content type is unchanged');

const expectedKeys = [
    'seenAt', 'browserName', 'browserVersion', 'osName', 'osVersion', 'platform', 'language', 'timezone',
    'screenWidth', 'screenHeight', 'availScreenWidth', 'availScreenHeight', 'viewportWidth', 'viewportHeight',
    'devicePixelRatio', 'colorDepth', 'maxTouchPoints', 'hardwareConcurrency', 'deviceMemory',
    'screenOrientation', 'online', 'cookieEnabled', 'userAgent',
];
assert(JSON.stringify(Object.keys(requests[0].payload)) === JSON.stringify(expectedKeys), 'heartbeat payload fields are unchanged');
assert(scheduled.some(item => item.delay === 60000), 'successful heartbeat schedules the configured interval');
assert(window.__huginHeartbeatStatus.lastSuccessAt > 0, 'successful heartbeat time is exposed');
assert(window.__huginHeartbeatStatus.requestInFlight === false, 'successful heartbeat releases the in-flight latch');
assert(listeners.has('window:online') && listeners.has('window:pageshow') && listeners.has('document:visibilitychange'), 'recovery lifecycle listeners are installed');

console.log('PASS heartbeat module contract and recovery hooks');
