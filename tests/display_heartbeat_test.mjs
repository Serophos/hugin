import {
    assert,
    createDeferredResponse,
    flushPromises,
    installHeartbeatHarness,
} from './display_heartbeat_harness.mjs';

const harness = installHeartbeatHarness({ intervalSeconds: 60 });
await import('../public/assets/js/display-heartbeat.js?contract-and-lifecycle');
await flushPromises();

const expectedKeys = [
    'seenAt', 'browserName', 'browserVersion', 'osName', 'osVersion', 'platform', 'language', 'timezone',
    'screenWidth', 'screenHeight', 'availScreenWidth', 'availScreenHeight', 'viewportWidth', 'viewportHeight',
    'devicePixelRatio', 'colorDepth', 'maxTouchPoints', 'hardwareConcurrency', 'deviceMemory',
    'screenOrientation', 'online', 'cookieEnabled', 'userAgent', 'playback',
];
const originalController = window.__huginHeartbeatController;
assert(harness.requests.length === 1, 'heartbeat sends immediately on startup');
assert(harness.requests[0].url === 'https://hugin.test/display/lobby/heartbeat', 'heartbeat URL stays same-origin');
assert(harness.requests[0].options.method === 'POST', 'heartbeat uses POST');
assert(harness.requests[0].options.headers['Content-Type'] === 'application/json', 'heartbeat sends JSON');
assert(JSON.stringify(Object.keys(harness.requests[0].payload)) === JSON.stringify(expectedKeys), 'payload contract is unchanged');
assert(
    JSON.stringify(harness.requests[0].payload.playback) === JSON.stringify({
        channelId: 17,
        channelName: 'Lobby Standard',
        stateSignature: '0123456789abcdef0123456789abcdef01234567',
        status: 'starting',
        pendingStateSignature: '',
        pendingActivationAtMs: 0,
    }),
    'heartbeat reports the playlist and state loaded by the display',
);
assert(originalController.status.successCount === 1, 'initial heartbeat succeeds');
assert(originalController.status.requestInFlight === false, 'successful request releases the latch');
assert(harness.clock.intervalCount() === 1, 'exactly one fixed cadence is installed');
assert(harness.clock.timeoutCount() === 0, 'settled requests leave no timeout behind');

for (let tick = 0; tick < 105; tick += 1) {
    await harness.clock.advance(60_000);
}
assert(harness.requests.length === 106, 'heartbeat remains alive for more than 100 cadence ticks');
assert(originalController.status.successCount === 106, 'every long-run cadence request settles');
assert(harness.clock.intervalCount() === 1, 'long-running heartbeat never duplicates its cadence');

const deferred = createDeferredResponse();
harness.setFetchHandler(request => {
    request.options.signal?.addEventListener('abort', () => deferred.reject(new Error('aborted')), { once: true });
    return deferred.promise;
});
harness.emitWindow('online');
await flushPromises();
const pendingRequestCount = harness.requests.length;
harness.emitWindow('focus');
harness.emitWindow('pageshow', { persisted: false });
harness.emitDocument('visibilitychange');
harness.emitOrientation('change');
harness.emitWindow('resize');
await harness.clock.advance(600);
assert(harness.requests.length === pendingRequestCount, 'lifecycle bursts share one in-flight request');
assert(harness.maxActiveFetches() === 1, 'at most one transport request is active');
assert(originalController.status.skippedCount >= 5, 'guarded lifecycle sends are diagnosed');
deferred.resolve({ ok: true, status: 200 });
await flushPromises();
assert(originalController.status.requestInFlight === false, 'lifecycle request releases the latch');

const beforeBfcacheRequests = harness.requests.length;
harness.emitWindow('pagehide', { persisted: true });
assert(originalController.status.pausedForBfcache === true, 'persisted pagehide pauses the controller');
assert(harness.clock.intervalCount() === 0, 'BFCache pause clears the cadence');
assert(harness.beacons.length === 0, 'BFCache pause does not overlap its restore send with an unload beacon');
harness.setFetchHandler(() => Promise.resolve({ ok: true, status: 200 }));
harness.emitWindow('pageshow', { persisted: true });
await flushPromises();
assert(originalController.status.pausedForBfcache === false, 'persisted pageshow resumes the controller');
assert(harness.clock.intervalCount() === 1, 'BFCache resume installs one cadence');
assert(harness.requests.length === beforeBfcacheRequests + 1, 'BFCache resume sends once immediately');

const oldStatus = originalController.status;
let replacementAttempt = 0;
harness.setFetchHandler(request => {
    replacementAttempt += 1;
    if (replacementAttempt > 1) return Promise.resolve({ ok: true, status: 200 });
    return new Promise((resolve, reject) => {
        request.options.signal?.addEventListener('abort', () => reject(new Error('replaced')), { once: true });
    });
});
harness.emitWindow('online');
await flushPromises();
assert(originalController.status.requestInFlight === true, 'old controller has an active request before replacement');
await import('../public/assets/js/display-heartbeat.js?replacement-controller');
await flushPromises();
const replacementController = window.__huginHeartbeatController;
assert(replacementController !== originalController, 'script reevaluation creates a fresh controller');
assert(oldStatus.running === false && oldStatus.stopReason === 'replaced', 'script reevaluation stops the old owner');
assert(harness.clock.intervalCount() === 1, 'script reevaluation still leaves exactly one cadence');
assert(harness.maxActiveFetches() === 1, 'replacement waits for the aborted transport to settle');
assert(replacementController.status.successCount === 1, 'replacement sends immediately after old transport cleanup');
for (const eventName of ['online', 'focus', 'pageshow', 'pagehide', 'resize']) {
    assert(harness.listenerCount('window', eventName) === 1, `${eventName} has one listener after reevaluation`);
}
assert(harness.listenerCount('document', 'visibilitychange') === 1, 'visibility has one listener after reevaluation');
assert(harness.listenerCount('orientation', 'change') === 1, 'orientation has one listener after reevaluation');

const requestsBeforeStop = harness.requests.length;
replacementController.stop({ reason: 'test-shutdown' });
assert(replacementController.status.running === false, 'intentional stop updates diagnostics');
assert(harness.clock.intervalCount() === 0, 'intentional stop clears the cadence');
assert(harness.totalListeners() === 0, 'intentional stop removes every listener');
await harness.clock.advance(60_000 * 5);
assert(harness.requests.length === requestsBeforeStop, 'no heartbeats run after intentional shutdown');

console.log('PASS heartbeat fixed cadence, lifecycle ownership, BFCache, remount, and 100+ tick endurance');
