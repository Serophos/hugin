import {
    assert,
    flushPromises,
    installHeartbeatHarness,
    pendingUntilAbort,
} from './display_heartbeat_harness.mjs';

const harness = installHeartbeatHarness({
    intervalSeconds: 30,
    initialFetchHandler: pendingUntilAbort,
});
await import('../public/assets/js/display-heartbeat.js?failure-recovery');
await flushPromises();
const controller = window.__huginHeartbeatController;
assert(controller.status.requestInFlight === true, 'initial pending heartbeat is tracked');
assert(harness.clock.intervalCount() === 1, 'pending request does not create another cadence');

await harness.clock.advance(15_000);
assert(controller.status.requestInFlight === false, 'request timeout releases the latch');
assert(controller.status.consecutiveFailures === 1, 'timeout records one failure');
assert(controller.status.lastError.includes('timed out'), 'timeout reason is exposed');
assert(harness.warnings.length === 1, 'first unexpected failure is surfaced');

harness.setFetchHandler(() => Promise.resolve({ ok: true, status: 200 }));
await harness.clock.advance(15_000);
assert(harness.requests.length === 2, 'the unchanged cadence retries after a transient timeout');
assert(controller.status.consecutiveFailures === 0, 'successful cadence clears the failure streak');
assert(controller.status.successCount === 1, 'recovery is recorded');
assert(harness.infos.length === 1, 'recovery is surfaced');

harness.setFetchHandler(() => Promise.resolve({ ok: false, status: 503 }));
harness.emitWindow('online');
await flushPromises();
assert(controller.status.consecutiveFailures === 1, 'HTTP failure is recorded');
assert(controller.status.lastError.includes('HTTP 503'), 'HTTP status is exposed');

harness.setFetchHandler(() => { throw new Error('synchronous transport failure'); });
harness.emitWindow('focus');
await flushPromises();
assert(controller.status.requestInFlight === false, 'synchronous fetch error releases the latch');
assert(controller.status.consecutiveFailures === 2, 'synchronous fetch error advances the failure streak');
assert(controller.status.lastError.includes('synchronous transport failure'), 'synchronous error is exposed');

harness.setFetchHandler(pendingUntilAbort);
harness.emitWindow('online');
await flushPromises();
const beforeBurst = harness.requests.length;
harness.emitWindow('focus');
harness.emitWindow('pageshow', { persisted: false });
harness.emitDocument('visibilitychange');
assert(harness.requests.length === beforeBurst, 'failures and lifecycle bursts never duplicate an in-flight request');
assert(harness.maxActiveFetches() === 1, 'failure recovery keeps one active transport maximum');

// Simulate a browser waking after timers were frozen: wall-clock time advances,
// but the request timeout callback has not had an opportunity to run.
harness.clock.now += controller.status.requestTimeoutMs + 1;
harness.setFetchHandler(() => Promise.resolve({ ok: true, status: 200 }));
const requestsBeforeStaleRecovery = harness.requests.length;
harness.emitWindow('focus');
harness.emitWindow('pageshow', { persisted: false });
await flushPromises();
assert(harness.requests.length === requestsBeforeStaleRecovery + 1, 'stale recovery coalesces simultaneous wake events');
assert(controller.status.requestInFlight === false, 'focus recovers a stale latch after timer throttling');
assert(controller.status.lastError === '', 'the replacement request recovers successfully');
assert(controller.status.consecutiveFailures === 0, 'stale-latch recovery clears after success');
assert(harness.maxActiveFetches() === 1, 'stale-latch recovery aborts before replacing the request');

const requestsBeforeShutdown = harness.requests.length;
harness.emitWindow('pagehide', { persisted: false });
assert(harness.beacons.length === 1, 'intentional page shutdown queues one beacon');
assert(controller.status.running === false, 'non-persisted pagehide stops the controller');
assert(harness.clock.intervalCount() === 0, 'shutdown clears the cadence');
assert(harness.totalListeners() === 0, 'shutdown removes lifecycle listeners');
await harness.clock.advance(30_000 * 10);
assert(harness.requests.length === requestsBeforeShutdown, 'shutdown cannot be resurrected by old timers');


const nativeAbortController = window.AbortController;
window.AbortController = undefined;
let xhrMode = 'pending';
let xhrAbortCount = 0;
let xhrSendCount = 0;
window.XMLHttpRequest = class FakeXMLHttpRequest {
    constructor() {
        this.status = 0;
        this.headers = {};
    }

    open(method, url) {
        this.method = method;
        this.url = url;
    }

    setRequestHeader(name, value) {
        this.headers[name] = value;
    }

    send(body) {
        this.body = body;
        xhrSendCount += 1;
        if (xhrMode === 'success') {
            this.status = 200;
            this.onload();
        }
    }

    abort() {
        xhrAbortCount += 1;
        this.onabort?.();
    }
};

await import('../public/assets/js/display-heartbeat.js?xhr-without-abort-controller');
await flushPromises();
const xhrController = window.__huginHeartbeatController;
assert(xhrController.status.requestInFlight === true, 'XHR fallback tracks its in-flight request');
assert(xhrSendCount === 1, 'XHR fallback sends when AbortController is unavailable');
await harness.clock.advance(xhrController.status.requestTimeoutMs);
assert(xhrAbortCount === 1, 'XHR fallback is aborted at the request deadline');
assert(xhrController.status.requestInFlight === false, 'XHR timeout releases the latch without AbortController');
assert(xhrController.status.lastError.includes('timed out'), 'XHR timeout retains the deadline diagnostic');
xhrMode = 'success';
await harness.clock.advance(xhrController.status.intervalMs - xhrController.status.requestTimeoutMs);
assert(xhrSendCount === 2, 'fixed cadence retries through the XHR fallback');
assert(xhrController.status.successCount === 1, 'XHR fallback recovers on a later success');
xhrController.stop({ reason: 'xhr-test-complete' });

window.AbortController = nativeAbortController;
delete window.XMLHttpRequest;
const originalDateTimeFormat = Intl.DateTimeFormat;
Intl.DateTimeFormat = () => { throw new Error('timezone lookup failed'); };
const requestCountBeforePayloadFailure = harness.requests.length;
await import('../public/assets/js/display-heartbeat.js?payload-failure');
await flushPromises();
const payloadController = window.__huginHeartbeatController;
assert(payloadController.status.consecutiveFailures === 1, 'payload exception is recorded instead of terminating startup');
assert(payloadController.status.lastError.includes('timezone lookup failed'), 'payload exception is exposed');
assert(harness.requests.length === requestCountBeforePayloadFailure, 'invalid payload never starts a transport');
assert(harness.clock.intervalCount() === 1, 'payload exception leaves the fixed cadence alive');
Intl.DateTimeFormat = originalDateTimeFormat;
harness.setFetchHandler(() => Promise.resolve({ ok: true, status: 200 }));
harness.emitWindow('online');
await flushPromises();
assert(payloadController.status.successCount === 1, 'lifecycle send recovers after a payload exception');
assert(payloadController.status.consecutiveFailures === 0, 'payload recovery clears its failure streak');
payloadController.stop({ reason: 'payload-test-complete' });

harness.setFetchHandler(() => new Promise(() => {}));
await import('../public/assets/js/display-heartbeat.js?non-settling-abort');
await flushPromises();
const brokenAbortController = window.__huginHeartbeatController;
assert(brokenAbortController.status.requestInFlight === true, 'non-settling transport starts normally');
harness.clock.now += brokenAbortController.status.requestTimeoutMs + 1;
harness.setFetchHandler(() => Promise.resolve({ ok: true, status: 200 }));
harness.emitWindow('focus');
assert(brokenAbortController.status.requestReason === 'recovering', 'stale non-settling abort enters bounded recovery');
await harness.clock.advance(brokenAbortController.status.transportCleanupTimeoutMs);
assert(brokenAbortController.status.cleanupTimeoutCount === 1, 'broken abort settlement reaches its explicit bound');
assert(brokenAbortController.status.successCount === 1, 'heartbeat resumes after the bounded cleanup wait');
assert(brokenAbortController.status.requestInFlight === false, 'bounded cleanup cannot wedge the logical request owner');
brokenAbortController.stop({ reason: 'broken-abort-test-complete' });

let tripleRemountAttempt = 0;
harness.setFetchHandler(() => {
    tripleRemountAttempt += 1;
    return tripleRemountAttempt === 1
        ? new Promise(() => {})
        : Promise.resolve({ ok: true, status: 200 });
});
await import('../public/assets/js/display-heartbeat.js?triple-remount-a');
await flushPromises();
const tripleControllerA = window.__huginHeartbeatController;
assert(tripleControllerA.status.requestInFlight === true, 'triple-remount source owns a non-settling request');
await import('../public/assets/js/display-heartbeat.js?triple-remount-b');
const tripleControllerB = window.__huginHeartbeatController;
assert(tripleControllerB.status.running === false, 'middle controller waits for predecessor cleanup');
await import('../public/assets/js/display-heartbeat.js?triple-remount-c');
const tripleControllerC = window.__huginHeartbeatController;
assert(tripleControllerB.status.disposed === true, 'third evaluation disposes the not-yet-started middle controller');
assert(tripleControllerC.status.running === false, 'third controller inherits the bounded predecessor cleanup gate');
await harness.clock.advance(tripleControllerA.status.transportCleanupTimeoutMs);
assert(tripleControllerA.status.cleanupTimeoutCount === 1, 'triple-remount predecessor reaches its bounded cleanup');
assert(tripleControllerB.status.running === false, 'disposed middle controller cannot resurrect after cleanup');
assert(tripleControllerC.status.running === true, 'latest controller starts after bounded predecessor cleanup');
assert(window.__huginHeartbeatController === tripleControllerC, 'latest controller retains global ownership');
assert(tripleRemountAttempt === 2, 'disposed middle controller never sends a heartbeat');
assert(tripleControllerC.status.successCount === 1, 'latest controller sends once after cleanup');
assert(harness.clock.intervalCount() === 1, 'triple reevaluation leaves one cadence');
for (const eventName of ['online', 'focus', 'pageshow', 'pagehide', 'resize']) {
    assert(harness.listenerCount('window', eventName) === 1, `triple reevaluation leaves one ${eventName} listener`);
}
assert(harness.listenerCount('document', 'visibilitychange') === 1, 'triple reevaluation leaves one visibility listener');
assert(harness.listenerCount('orientation', 'change') === 1, 'triple reevaluation leaves one orientation listener');
tripleControllerC.stop({ reason: 'triple-remount-test-complete' });

console.log('PASS heartbeat timeout, HTTP, synchronous, sleep/throttle, transient recovery, no-AbortController, payload exception, bounded abort cleanup, triple remount, and shutdown handling');
