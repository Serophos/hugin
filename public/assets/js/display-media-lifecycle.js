((root) => {
    'use strict';

    const STATES = Object.freeze({
        IDLE: 'idle',
        LOADING: 'loading',
        READY: 'ready',
        FAILED: 'failed',
        DISPOSED: 'disposed',
    });

    const create = (options = {}) => {
        const host = options.host || root;
        const setTimer = options.setTimeout || host.setTimeout.bind(host);
        const clearTimer = options.clearTimeout || host.clearTimeout.bind(host);
        const now = options.now || (() => Date.now());
        const defaultTimeoutMs = Math.max(1, Number(options.timeoutMs || 8000));
        const defaultRetryDelayMs = Math.max(0, Number(options.retryDelayMs || 0));
        const records = new WeakMap();
        const liveRecords = new Set();

        const recordFor = element => {
            let record = records.get(element);
            if (record) return record;

            record = {
                element,
                state: STATES.IDLE,
                attempt: 0,
                source: '',
                promise: null,
                resolve: null,
                timeout: null,
                listeners: [],
                monitor: null,
                failedAt: 0,
                retryAt: 0,
                failureReason: '',
                blankPromise: null,
                blankCleanup: null,
                blankUrl: '',
                required: true,
            };
            records.set(element, record);
            liveRecords.add(record);
            return record;
        };

        const removeListeners = record => {
            record.listeners.forEach(([type, listener]) => {
                record.element.removeEventListener(type, listener);
            });
            record.listeners = [];
            if (record.timeout !== null) {
                clearTimer(record.timeout);
                record.timeout = null;
            }
        };

        const removeMonitor = record => {
            if (!record.monitor) return;
            record.element.removeEventListener('error', record.monitor);
            record.monitor = null;
        };

        const setDiagnosticState = record => {
            if (!record.element.dataset) return;
            record.element.dataset.mediaState = record.state;
            record.element.dataset.mediaAttempt = String(record.attempt);
            if (record.state === STATES.FAILED) {
                record.element.dataset.mediaFailedAt = String(record.failedAt);
                record.element.dataset.mediaRetryAt = String(record.retryAt);
                record.element.dataset.mediaFailureReason = record.failureReason;
            } else {
                delete record.element.dataset.mediaFailedAt;
                delete record.element.dataset.mediaRetryAt;
                delete record.element.dataset.mediaFailureReason;
            }
        };

        const sourceMatches = record => (
            record.element.getAttribute('src') === record.source
            && record.source !== ''
            && record.source !== 'about:blank'
        );

        const notify = (name, record, context = {}) => {
            const callback = options[name];
            if (typeof callback !== 'function') return;
            callback(record.element, Object.assign(inspect(record.element), context));
        };

        const markFailed = (record, token, reason, allowReady = false) => {
            if (
                record.attempt !== token
                || (record.state !== STATES.LOADING && !(allowReady && record.state === STATES.READY))
            ) {
                return false;
            }

            const resolver = record.resolve;
            removeListeners(record);
            removeMonitor(record);
            cancelIframeReset(record);
            record.state = STATES.FAILED;
            record.promise = null;
            record.resolve = null;
            record.failedAt = now();
            record.retryAt = record.failedAt + defaultRetryDelayMs;
            record.failureReason = String(reason || 'media-error');
            record.element.classList?.add('is-media-error');
            setDiagnosticState(record);
            if (resolver) resolver(false);
            notify('onFailure', record, { reason: record.failureReason });
            return true;
        };

        const monitorReadyElement = record => {
            removeMonitor(record);
            const token = record.attempt;
            record.monitor = () => {
                if (record.attempt !== token || record.state !== STATES.READY || !sourceMatches(record)) {
                    return;
                }
                markFailed(record, token, 'error-after-ready', true);
            };
            record.element.addEventListener('error', record.monitor);
        };

        const markReady = (record, token) => {
            if (record.attempt !== token || record.state !== STATES.LOADING || !sourceMatches(record)) {
                return false;
            }

            const resolver = record.resolve;
            removeListeners(record);
            record.state = STATES.READY;
            record.promise = null;
            record.resolve = null;
            record.failedAt = 0;
            record.retryAt = 0;
            record.failureReason = '';
            record.element.classList?.remove('is-media-error');
            setDiagnosticState(record);
            monitorReadyElement(record);
            if (resolver) resolver(true);
            notify('onReady', record);
            return true;
        };

        const decodeThenReady = (record, token) => {
            if (record.attempt !== token || record.state !== STATES.LOADING || !sourceMatches(record)) {
                return;
            }

            if (typeof record.element.decode !== 'function') {
                markReady(record, token);
                return;
            }

            let decoded;
            try {
                decoded = record.element.decode();
            } catch (error) {
                markFailed(record, token, 'decode-error');
                return;
            }
            Promise.resolve(decoded).then(
                () => markReady(record, token),
                () => markFailed(record, token, 'decode-error')
            );
        };

        const currentElementReady = element => {
            const tag = String(element.tagName || '').toUpperCase();
            if (tag === 'IMG') {
                return Boolean(element.complete) && Number(element.naturalWidth || 0) > 0;
            }
            if (tag === 'VIDEO') {
                return Number(element.readyState || 0) >= 2;
            }
            return false;
        };

        const readableFrameLocation = element => {
            try {
                return String(element.contentWindow?.location?.href || '');
            } catch (error) {
                return null;
            }
        };

        const cancelIframeReset = record => {
            record.blankCleanup?.();
            record.blankCleanup = null;
            record.blankPromise = null;
            record.blankUrl = '';
        };

        const resetIframe = record => {
            const element = record.element;
            cancelIframeReset(record);
            // Fragment-only about:blank changes do not emit load in Chromium.
            // A unique query forces a real, still-readable blank navigation.
            const resetUrl = `about:blank?hugin-media-reset=${record.attempt}`;
            record.blankUrl = resetUrl;
            record.blankPromise = new Promise(resolve => {
                let settled = false;
                const loaded = () => {
                    if (settled || element.getAttribute('src') !== resetUrl) return;
                    if (readableFrameLocation(element) !== resetUrl) return;
                    settled = true;
                    cleanup();
                    resolve();
                };
                const cleanup = () => {
                    element.removeEventListener('load', loaded);
                    if (record.blankCleanup === cleanup) record.blankCleanup = null;
                };
                record.blankCleanup = cleanup;
                element.addEventListener('load', loaded);
                element.setAttribute('src', resetUrl);
            });
            return record.blankPromise;
        };

        const invalidate = (record, unload) => {
            const resolver = record.resolve;
            cancelIframeReset(record);
            record.attempt += 1;
            removeListeners(record);
            removeMonitor(record);
            record.promise = null;
            record.resolve = null;
            if (resolver) resolver(false);

            let reset = null;
            if (unload) {
                const tag = String(record.element.tagName || '').toUpperCase();
                if (tag === 'IFRAME') {
                    reset = resetIframe(record);
                } else {
                    record.element.removeAttribute('src');
                }
                if (tag === 'VIDEO') {
                    try {
                        record.element.load();
                    } catch (error) {}
                }
            }
            return reset;
        };

        const prepare = (element, prepareOptions = {}) => {
            if (!element) return Promise.resolve(false);
            const source = String(prepareOptions.source || element.dataset?.src || '').trim();
            if (!source) return Promise.resolve(false);

            const record = recordFor(element);
            const retry = prepareOptions.retry === true;
            const sourceChanged = record.source !== '' && record.source !== source;
            const tag = String(element.tagName || '').toUpperCase();
            record.required = prepareOptions.required !== false;

            if (!sourceChanged && record.state === STATES.LOADING && record.promise) {
                return record.promise;
            }
            if (!sourceChanged && record.state === STATES.READY && sourceMatches(record)) {
                return Promise.resolve(true);
            }
            if (!sourceChanged && record.state === STATES.FAILED && !retry) {
                return Promise.resolve(false);
            }

            const iframeReset = invalidate(record, retry || sourceChanged || record.state === STATES.DISPOSED);
            record.source = source;
            record.state = STATES.LOADING;
            record.attempt += 1;
            record.failedAt = 0;
            record.retryAt = 0;
            record.failureReason = '';
            element.classList?.remove('is-media-error');
            setDiagnosticState(record);
            const token = record.attempt;

            record.promise = new Promise(resolve => {
                record.resolve = resolve;
            });
            const promise = record.promise;
            const timeoutMs = Math.max(1, Number(prepareOptions.timeoutMs || defaultTimeoutMs));
            record.timeout = setTimer(() => markFailed(record, token, 'timeout'), timeoutMs);

            const loaded = () => {
                if (record.attempt !== token || record.state !== STATES.LOADING || !sourceMatches(record)) {
                    return;
                }
                if (tag === 'IFRAME') {
                    const frameLocation = readableFrameLocation(element);
                    if (typeof frameLocation === 'string' && frameLocation.startsWith('about:blank')) return;
                }
                if (tag === 'IMG') {
                    if (!currentElementReady(element)) {
                        markFailed(record, token, 'empty-image');
                        return;
                    }
                    decodeThenReady(record, token);
                    return;
                }
                if (tag === 'VIDEO' && !currentElementReady(element)) {
                    return;
                }
                markReady(record, token);
            };
            const failed = () => {
                if (!sourceMatches(record)) return;
                markFailed(record, token, 'load-error');
            };
            const readyEvent = tag === 'VIDEO' ? 'canplay' : 'load';
            const startNavigation = () => {
                if (record.attempt !== token || record.state !== STATES.LOADING) return;
                record.blankPromise = null;
                record.blankCleanup = null;
                record.blankUrl = '';
                element.addEventListener(readyEvent, loaded);
                element.addEventListener('error', failed);
                record.listeners.push([readyEvent, loaded], ['error', failed]);

                if (tag === 'IFRAME') {
                    element.setAttribute('loading', 'eager');
                }

                if (element.getAttribute('src') !== source || retry) {
                    element.setAttribute('src', source);
                    if (tag === 'VIDEO') {
                        try {
                            element.load();
                        } catch (error) {}
                    }
                }

                // A parser- or plugin-assigned image may already have failed before
                // this controller was initialized. It must enter FAILED, not READY.
                if (tag === 'IMG' && element.complete) {
                    if (Number(element.naturalWidth || 0) > 0) {
                        decodeThenReady(record, token);
                    } else {
                        markFailed(record, token, 'empty-image');
                    }
                } else if (tag === 'VIDEO' && currentElementReady(element)) {
                    markReady(record, token);
                }
            };
            if (iframeReset) iframeReset.then(startNavigation);
            else startNavigation();

            return promise;
        };

        const retry = (element, retryOptions = {}) => {
            const record = element ? recordFor(element) : null;
            if (!record) return Promise.resolve(false);
            const minimumDelayMs = Math.max(0, Number(retryOptions.minimumDelayMs ?? defaultRetryDelayMs));
            record.retryAt = record.failedAt + minimumDelayMs;
            setDiagnosticState(record);
            if (record.state === STATES.FAILED && now() - record.failedAt < minimumDelayMs) {
                return Promise.resolve(false);
            }
            return prepare(element, Object.assign({}, retryOptions, { retry: true }));
        };

        const dispose = (element, disposeOptions = {}) => {
            if (!element) return;
            const record = recordFor(element);
            invalidate(record, disposeOptions.unload === true);
            record.state = STATES.DISPOSED;
            record.failedAt = 0;
            record.retryAt = 0;
            record.failureReason = '';
            element.classList?.remove('is-media-error');
            setDiagnosticState(record);
        };

        const inspect = element => {
            const record = element ? records.get(element) : null;
            if (!record) {
                return {
                    state: STATES.IDLE,
                    attempt: 0,
                    source: '',
                    failedAt: 0,
                    retryAt: 0,
                    failureReason: '',
                    required: true,
                };
            }
            return {
                state: record.state,
                attempt: record.attempt,
                source: record.source,
                failedAt: record.failedAt,
                retryAt: record.retryAt,
                failureReason: record.failureReason,
                required: record.required,
            };
        };

        const requiredElements = slide => {
            if (!slide?.querySelectorAll) return [];
            return Array.from(slide.querySelectorAll('img[data-src], video[data-src], iframe[data-src]'))
                .filter(element => !element.classList?.contains('text-slide-background'));
        };

        const prepareSlide = (slide, prepareOptions = {}) => {
            const elements = requiredElements(slide);
            if (elements.length === 0) return Promise.resolve(true);
            const tasks = elements.map(element => {
                if (prepareOptions.retryFailed === true && inspect(element).state === STATES.FAILED) {
                    return retry(element, {
                        required: true,
                        timeoutMs: prepareOptions.timeoutMs,
                        minimumDelayMs: prepareOptions.minimumDelayMs,
                    });
                }
                return prepare(element, {
                    required: true,
                    timeoutMs: prepareOptions.timeoutMs,
                });
            });
            return Promise.all(tasks).then(results => results.every(Boolean));
        };

        const isSlideReady = slide => {
            const elements = requiredElements(slide);
            return elements.every(element => {
                const record = records.get(element);
                return record?.state === STATES.READY && sourceMatches(record);
            });
        };

        const findReadyCandidate = async (slides, startIndex = 0, findOptions = {}) => {
            const candidates = Array.from(slides || []);
            if (candidates.length === 0) return -1;
            const first = Number.isInteger(startIndex) && startIndex >= 0
                ? startIndex % candidates.length
                : 0;
            const isEligible = typeof findOptions.isEligible === 'function'
                ? findOptions.isEligible
                : (() => true);
            const shouldContinue = typeof findOptions.shouldContinue === 'function'
                ? findOptions.shouldContinue
                : (() => true);

            for (let step = 0; step < candidates.length; step += 1) {
                if (!shouldContinue()) return -1;
                const candidateIndex = (first + step) % candidates.length;
                if (candidateIndex === findOptions.excludeIndex) continue;
                const candidate = candidates[candidateIndex];
                if (!isEligible(candidate, candidateIndex)) continue;

                const ready = await prepareSlide(candidate, {
                    retryFailed: findOptions.retryFailed === true,
                    minimumDelayMs: findOptions.minimumDelayMs,
                    timeoutMs: findOptions.timeoutMs,
                });
                if (!shouldContinue()) return -1;
                if (ready && isEligible(candidate, candidateIndex) && isSlideReady(candidate)) {
                    return candidateIndex;
                }
                findOptions.onRejected?.(candidate, candidateIndex);
            }
            return -1;
        };

        const handoffVisible = (current, next, handoffOptions = {}) => {
            if (!next || !isSlideReady(next)) return false;
            const activeClass = handoffOptions.activeClass || 'is-active';
            const currentWasActive = current?.classList?.contains(activeClass) || false;
            const nextWasActive = next.classList?.contains(activeClass) || false;
            const restore = (element, active) => {
                if (!element?.classList) return;
                if (active) element.classList.add(activeClass);
                else element.classList.remove(activeClass);
            };

            try {
                next.classList.add(activeClass);
                handoffOptions.afterAdd?.(current, next);
                if (current && current !== next) {
                    handoffOptions.beforeRemove?.(current, next);
                    current.classList.remove(activeClass);
                }
                handoffOptions.afterRemove?.(current, next);
                return true;
            } catch (error) {
                restore(next, nextWasActive);
                restore(current, currentWasActive);
                handoffOptions.onRollback?.(error, current, next);
                throw error;
            }
        };

        const destroy = () => {
            Array.from(liveRecords).forEach(record => {
                dispose(record.element, { unload: false });
                cancelIframeReset(record);
                liveRecords.delete(record);
            });
        };

        return Object.freeze({
            STATES,
            prepare,
            retry,
            prepareSlide,
            findReadyCandidate,
            handoffVisible,
            requiredElements,
            isSlideReady,
            inspect,
            dispose,
            destroy,
        });
    };

    root.HuginDisplayMedia = Object.freeze({ STATES, create });
})(typeof window !== 'undefined' ? window : globalThis);
