(() => {
    const previousController = window.__huginHeartbeatController;
    let previousStopResult = null;
    if (previousController && typeof previousController.stop === 'function') {
        try {
            previousStopResult = previousController.stop({ reason: 'replaced' });
        } catch (error) {
            window.console?.warn?.('[Hugin heartbeat] previous controller cleanup failed', error);
        }
    }
    window.__huginHeartbeatController = null;
    window.__huginHeartbeatStatus = null;

    const slideshow = document.getElementById('slideshow');
    if (!slideshow) return;

    const resolveEndpointUrl = value => {
        if (!value) return '';
        try {
            const endpoint = new URL(value, window.location.href);
            if (endpoint.origin !== window.location.origin) {
                endpoint.protocol = window.location.protocol;
                endpoint.host = window.location.host;
            }
            return endpoint.toString();
        } catch (error) {
            return value;
        }
    };

    const heartbeatUrl = resolveEndpointUrl(slideshow.dataset.heartbeatUrl);
    if (!heartbeatUrl) return;

    const configuredSeconds = parseInt(slideshow.dataset.heartbeatInterval || '90', 10);
    const intervalMs = Math.max(configuredSeconds || 90, 30) * 1000;
    const requestTimeoutMs = Math.min(20000, Math.max(10000, Math.floor(intervalMs / 2)));
    const transportCleanupTimeoutMs = Math.min(2000, Math.max(1000, Math.floor(requestTimeoutMs / 10)));
    const ua = navigator.userAgent || '';
    const listeners = [];
    let cadenceTimer = null;
    let resizeTimer = null;
    let activeRequest = null;
    let recoveryPromise = null;
    let requestSequence = 0;
    let running = false;
    let disposed = false;
    let pausedForBfcache = false;

    const status = {
        heartbeatUrl,
        intervalMs,
        requestTimeoutMs,
        transportCleanupTimeoutMs,
        running: false,
        disposed: false,
        pausedForBfcache: false,
        requestInFlight: false,
        requestReason: '',
        attemptCount: 0,
        successCount: 0,
        skippedCount: 0,
        beaconCount: 0,
        cleanupTimeoutCount: 0,
        consecutiveFailures: 0,
        lastAttemptAt: 0,
        lastSuccessAt: 0,
        lastFailureAt: 0,
        lastBeaconAt: 0,
        lastBeaconReason: '',
        lastCleanupTimeoutAt: 0,
        lastError: '',
        stopReason: '',
    };

    const syncStatus = () => Object.assign(status, {
        running,
        disposed,
        pausedForBfcache,
        requestInFlight: activeRequest !== null || recoveryPromise !== null,
        requestReason: activeRequest?.reason || (recoveryPromise ? 'recovering' : ''),
    });

    const warnFailure = error => {
        const previousFailures = status.consecutiveFailures;
        status.consecutiveFailures = previousFailures + 1;
        status.lastFailureAt = Date.now();
        status.lastError = String(error?.message || error || 'Heartbeat request failed');
        syncStatus();
        if (status.consecutiveFailures === 1 || status.consecutiveFailures % 5 === 0) {
            window.console?.warn?.('[Hugin heartbeat] request failed', {
                error: status.lastError,
                consecutiveFailures: status.consecutiveFailures,
                lastSuccessAt: status.lastSuccessAt ? new Date(status.lastSuccessAt).toISOString() : null,
            });
        }
    };

    const parseBrowser = () => {
        const checks = [
            { name: 'Edge', regex: /(Edg|Edge)\/([\d.]+)/i },
            { name: 'Opera', regex: /(OPR)\/([\d.]+)/i },
            { name: 'Chrome', regex: /(Chrome)\/([\d.]+)/i },
            { name: 'Firefox', regex: /(Firefox)\/([\d.]+)/i },
            { name: 'Safari', regex: /Version\/([\d.]+).*Safari/i },
        ];
        for (const item of checks) {
            const match = ua.match(item.regex);
            if (match) return { browserName: item.name, browserVersion: match[2] || match[1] || '' };
        }
        return { browserName: 'Unknown', browserVersion: '' };
    };

    const parseOs = () => {
        const platform = navigator.platform || '';
        const list = [
            { name: 'Windows', regex: /Windows NT ([\d.]+)/i },
            { name: 'Android', regex: /Android ([\d.]+)/i },
            { name: 'iOS', regex: /OS ([\d_]+) like Mac OS X/i, transform: value => value.replace(/_/g, '.') },
            { name: 'macOS', regex: /Mac OS X ([\d_]+)/i, transform: value => value.replace(/_/g, '.') },
            { name: 'Linux', regex: /Linux/i },
            { name: 'CrOS', regex: /CrOS [^ ]+ ([\d.]+)/i },
        ];
        for (const item of list) {
            const match = ua.match(item.regex);
            if (match) {
                return {
                    osName: item.name,
                    osVersion: match[1] ? (item.transform ? item.transform(match[1]) : match[1]) : '',
                    platform,
                };
            }
        }
        return { osName: platform || 'Unknown', osVersion: '', platform };
    };

    // Keep this payload contract in lock-step with the existing heartbeat API.
    const collectPayload = () => {
        const browser = parseBrowser();
        const os = parseOs();
        const screenOrientation = screen.orientation?.type || (window.innerHeight > window.innerWidth ? 'portrait' : 'landscape');
        return {
            seenAt: new Date().toISOString(),
            browserName: browser.browserName,
            browserVersion: browser.browserVersion,
            osName: os.osName,
            osVersion: os.osVersion,
            platform: navigator.platform || os.platform || '',
            language: navigator.language || '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            screenWidth: Number(screen.width || 0),
            screenHeight: Number(screen.height || 0),
            availScreenWidth: Number(screen.availWidth || 0),
            availScreenHeight: Number(screen.availHeight || 0),
            viewportWidth: Number(window.innerWidth || document.documentElement.clientWidth || 0),
            viewportHeight: Number(window.innerHeight || document.documentElement.clientHeight || 0),
            devicePixelRatio: Number(window.devicePixelRatio || 1),
            colorDepth: Number(screen.colorDepth || 0),
            maxTouchPoints: Number(navigator.maxTouchPoints || 0),
            hardwareConcurrency: Number(navigator.hardwareConcurrency || 0),
            deviceMemory: navigator.deviceMemory ? Number(navigator.deviceMemory) : null,
            screenOrientation,
            online: typeof navigator.onLine === 'boolean' ? navigator.onLine : null,
            cookieEnabled: typeof navigator.cookieEnabled === 'boolean' ? navigator.cookieEnabled : null,
            userAgent: ua,
        };
    };

    const payloadJson = () => JSON.stringify(collectPayload());

    const queueBeacon = (reason, payload = null) => {
        try {
            if (typeof navigator.sendBeacon !== 'function' || typeof Blob !== 'function') return false;
            const queued = navigator.sendBeacon(
                heartbeatUrl,
                new Blob([payload ?? payloadJson()], { type: 'application/json' }),
            );
            if (queued) {
                status.beaconCount += 1;
                status.lastBeaconAt = Date.now();
                status.lastBeaconReason = reason;
            }
            return queued;
        } catch (error) {
            window.console?.warn?.('[Hugin heartbeat] beacon could not be queued', error);
            return false;
        }
    };

    const awaitTransportCleanup = transportSettled => new Promise(resolve => {
        let finished = false;
        const finish = settled => {
            if (finished) return;
            finished = true;
            window.clearTimeout(cleanupTimer);
            resolve(settled);
        };
        const cleanupTimer = window.setTimeout(() => {
            status.cleanupTimeoutCount += 1;
            status.lastCleanupTimeoutAt = Date.now();
            window.console?.warn?.('[Hugin heartbeat] aborted transport did not settle', {
                timeoutMs: transportCleanupTimeoutMs,
            });
            finish(false);
        }, transportCleanupTimeoutMs);
        Promise.resolve(transportSettled).then(() => finish(true), () => finish(true));
    });

    const abortActiveRequest = () => {
        if (!activeRequest) return Promise.resolve(true);
        const request = activeRequest;
        activeRequest = null;
        requestSequence += 1;
        window.clearTimeout(request.timeoutTimer);
        request.abortTransport?.();
        syncStatus();
        return awaitTransportCleanup(request.transportSettled || Promise.resolve());
    };

    const send = ({ reason = 'manual' } = {}) => {
        if (!running || pausedForBfcache) return Promise.resolve(false);
        if (activeRequest || recoveryPromise) {
            status.skippedCount += 1;
            syncStatus();
            return Promise.resolve(false);
        }

        let payload;
        try {
            payload = payloadJson();
        } catch (error) {
            status.attemptCount += 1;
            status.lastAttemptAt = Date.now();
            warnFailure(error);
            return Promise.resolve(false);
        }

        status.attemptCount += 1;
        status.lastAttemptAt = Date.now();
        const canUseAbortableFetch = typeof window.fetch === 'function'
            && typeof window.AbortController === 'function';
        const canUseXhr = typeof window.XMLHttpRequest === 'function';
        if (!canUseAbortableFetch && !canUseXhr) {
            const queued = queueBeacon(reason, payload);
            if (queued) {
                status.successCount += 1;
                status.consecutiveFailures = 0;
                status.lastError = '';
            } else {
                warnFailure(new Error('No abortable heartbeat transport is available'));
            }
            syncStatus();
            return Promise.resolve(queued);
        }

        const sequence = ++requestSequence;
        let rejectTimeout;
        const timeoutPromise = new Promise((resolve, reject) => {
            rejectTimeout = reject;
        });
        const timeoutTimer = window.setTimeout(() => {
            rejectTimeout(new Error(`Heartbeat timed out after ${requestTimeoutMs}ms`));
            if (activeRequest?.sequence === sequence) activeRequest.abortTransport?.();
        }, requestTimeoutMs);

        activeRequest = {
            sequence,
            reason,
            timeoutTimer,
            startedAt: Date.now(),
            abortTransport: null,
            transportSettled: null,
        };
        syncStatus();

        let transportPromise;
        if (canUseAbortableFetch) {
            const controller = new window.AbortController();
            activeRequest.abortTransport = () => controller.abort();
            try {
                transportPromise = Promise.resolve(window.fetch(heartbeatUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: payload,
                    cache: 'no-store',
                    credentials: 'same-origin',
                    keepalive: true,
                    signal: controller.signal,
                }));
            } catch (error) {
                transportPromise = Promise.reject(error);
            }
        } else {
            transportPromise = new Promise((resolve, reject) => {
                try {
                    const xhr = new window.XMLHttpRequest();
                    activeRequest.abortTransport = () => xhr.abort();
                    xhr.open('POST', heartbeatUrl, true);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.onload = () => resolve({
                        ok: xhr.status >= 200 && xhr.status < 300,
                        status: xhr.status,
                    });
                    xhr.onerror = () => reject(new Error('Heartbeat network request failed'));
                    xhr.onabort = () => reject(new Error('Heartbeat request was aborted'));
                    xhr.send(payload);
                } catch (error) {
                    reject(error);
                }
            });
        }
        activeRequest.transportSettled = transportPromise.then(() => undefined, () => undefined);

        return Promise.race([transportPromise, timeoutPromise])
            .then(response => {
                if (!response?.ok) throw new Error(`Heartbeat failed with HTTP ${response?.status ?? 0}`);
                if (activeRequest?.sequence !== sequence) return false;
                const recoveredFailures = status.consecutiveFailures;
                status.successCount += 1;
                status.consecutiveFailures = 0;
                status.lastError = '';
                status.lastSuccessAt = Date.now();
                if (recoveredFailures > 0) {
                    window.console?.info?.('[Hugin heartbeat] connection recovered', { failures: recoveredFailures });
                }
                return true;
            })
            .catch(error => {
                if (activeRequest?.sequence !== sequence) return false;
                warnFailure(error);
                return false;
            })
            .finally(() => {
                window.clearTimeout(timeoutTimer);
                if (activeRequest?.sequence === sequence) {
                    activeRequest = null;
                    syncStatus();
                }
            });
    };

    const recoverAndSend = reason => {
        if (activeRequest && Date.now() - activeRequest.startedAt >= requestTimeoutMs) {
            const transportSettled = abortActiveRequest();
            warnFailure(new Error(`Recovered stale heartbeat after ${requestTimeoutMs}ms`));
            const pendingRecovery = transportSettled.then(() => {
                if (recoveryPromise !== pendingRecovery) return false;
                recoveryPromise = null;
                syncStatus();
                return send({ reason });
            });
            recoveryPromise = pendingRecovery;
            syncStatus();
            return pendingRecovery;
        }
        return send({ reason });
    };

    const clearCadence = () => {
        if (cadenceTimer !== null) {
            window.clearInterval(cadenceTimer);
            cadenceTimer = null;
        }
    };

    const armCadence = () => {
        clearCadence();
        cadenceTimer = window.setInterval(() => {
            recoverAndSend('cadence');
        }, intervalMs);
    };

    const listen = (target, eventName, handler) => {
        if (!target?.addEventListener) return;
        target.addEventListener(eventName, handler);
        listeners.push([target, eventName, handler]);
    };

    const pauseForBfcache = () => {
        pausedForBfcache = true;
        clearCadence();
        const hadActiveRequest = activeRequest !== null;
        const transportSettled = abortActiveRequest();
        if (hadActiveRequest) {
            const pendingRecovery = transportSettled.then(() => {
                if (recoveryPromise !== pendingRecovery) return false;
                recoveryPromise = null;
                syncStatus();
                if (running && !pausedForBfcache) return send({ reason: 'pageshow' });
                return false;
            });
            recoveryPromise = pendingRecovery;
        }
        syncStatus();
    };

    const resumeFromBfcache = () => {
        if (!running) return;
        pausedForBfcache = false;
        armCadence();
        syncStatus();
        recoverAndSend('pageshow');
    };

    const stop = ({ reason = 'intentional-shutdown', sendFinalBeacon = false } = {}) => {
        disposed = true;
        status.stopReason = reason;
        if (!running) {
            syncStatus();
            return previousStopResult && typeof previousStopResult.then === 'function'
                ? Promise.resolve(previousStopResult).then(() => undefined, () => undefined)
                : Promise.resolve();
        }
        running = false;
        pausedForBfcache = false;
        const pendingRecovery = recoveryPromise;
        recoveryPromise = null;
        clearCadence();
        if (resizeTimer !== null) {
            window.clearTimeout(resizeTimer);
            resizeTimer = null;
        }
        const transportSettled = abortActiveRequest();
        if (sendFinalBeacon) queueBeacon(reason);
        while (listeners.length > 0) {
            const [target, eventName, handler] = listeners.pop();
            target.removeEventListener?.(eventName, handler);
        }
        syncStatus();
        return Promise.all([
            transportSettled,
            pendingRecovery || Promise.resolve(),
        ].map(promise => Promise.resolve(promise).catch(() => undefined)));
    };

    const start = () => {
        if (running || disposed || window.__huginHeartbeatController !== controller) return;
        running = true;
        status.stopReason = '';
        syncStatus();

        listen(window, 'online', () => recoverAndSend('online'));
        listen(window, 'focus', () => recoverAndSend('focus'));
        listen(window, 'pageshow', event => {
            if (event?.persisted) {
                resumeFromBfcache();
            } else {
                recoverAndSend('pageshow');
            }
        });
        listen(window, 'pagehide', event => {
            if (event?.persisted) {
                pauseForBfcache();
            } else {
                stop({ reason: 'pagehide', sendFinalBeacon: true });
            }
        });
        listen(document, 'visibilitychange', () => {
            if (document.visibilityState === 'visible') recoverAndSend('visibilitychange');
        });
        listen(window, 'resize', () => {
            if (resizeTimer !== null) window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => {
                resizeTimer = null;
                recoverAndSend('resize');
            }, 600);
        });
        listen(screen.orientation, 'change', () => recoverAndSend('orientationchange'));

        armCadence();
        recoverAndSend('startup');
    };

    const controller = { stop, send, status };
    window.__huginHeartbeatController = controller;
    window.__huginHeartbeatStatus = status;
    if (previousStopResult && typeof previousStopResult.then === 'function') {
        Promise.resolve(previousStopResult).then(start, error => {
            window.console?.warn?.('[Hugin heartbeat] previous transport cleanup failed', error);
            start();
        });
    } else {
        start();
    }
})();
