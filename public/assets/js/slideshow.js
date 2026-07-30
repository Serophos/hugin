(() => {
    const slideshow = document.getElementById('slideshow');
    if (!slideshow) return;

    const slides = Array.from(document.querySelectorAll('.slide'));
    let index = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
    let timer = null;
    let stateTimer = null;
    let scheduleStateTimer = null;
    let selectionBoundaryTimer = null;
    let watchdogTimer = null;
    let pendingReloadTimer = null;
    let pendingReload = null;
    let nextSlideDueAt = 0;
    let startupComplete = false;
    let stateRequestInFlight = false;
    let stateRetryTimer = null;
    let stateFailureCount = 0;
    let transitionInFlight = false;
    let currentSignature = slideshow.dataset.stateSignature || '';
    let nextSelectionAtMs = Number(slideshow.dataset.nextSelectionAtMs || 0);
    const videoStartTimers = new WeakMap();
    const videoStartHandlers = new WeakMap();
    const MINUTE_MS = 60000;
    const SYNC_RELOAD_MIN_LEAD_MS = 3000;
    const SYNC_RELOAD_PAGE_LOAD_LEAD_MS = 5000;
    const CACHE_READINESS_POLL_MS = 2000;
    const CACHE_READINESS_MAX_WAIT_MS = 45000;
    const CACHE_READINESS_REQUEST_TIMEOUT_MS = 10000;
    const STATE_REQUEST_TIMEOUT_MS = 15000;
    const MEDIA_FAILURE_RETRY_MS = 30000;
    const MEDIA_READY_TIMEOUT_MS = 8000;
    const STARTUP_MAX_WAIT_MS = 45000;
    const SCHEDULED_SYNC_RELOAD_KEY = 'huginScheduledSyncReload';
    const SCHEDULED_SYNC_RELOAD_MAX_AGE_MS = 120000;
    let serverClockOffsetMs = 0;
    const startupStatus = slideshow.querySelector('.startup-loading__status');
    const startupProgress = slideshow.querySelector('[data-startup-cache-progress]');
    const startupProgressBar = slideshow.querySelector('[data-startup-progress-bar]');
    const startupProgressTrack = startupProgressBar?.closest('[role="progressbar"]') || null;
    const requestFrame = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : (callback => window.setTimeout(callback, 16));

    const loadingStages = {
        preparing: slideshow.dataset.loadingStagePreparing || 'Preparing offline support...',
        caching: slideshow.dataset.loadingStageCaching || 'Caching slideshow media...',
        degraded: slideshow.dataset.loadingStageDegraded || 'Continuing with limited offline cache...',
        ready: slideshow.dataset.loadingStageReady || 'Slideshow media is ready.',
        waitingGroup: slideshow.dataset.loadingStageWaitingGroup || 'Waiting for synchronized displays...',
        waitingMinute: slideshow.dataset.loadingStageWaitingMinute || 'Starting at the next full minute...',
        starting: slideshow.dataset.loadingStageStarting || 'Starting slideshow...',
    };

    const progressTemplate = slideshow.dataset.loadingProgressTemplate || ':completed of :total items prepared';
    const groupProgressTemplate = slideshow.dataset.loadingGroupProgressTemplate || ':completed of :total displays ready';
    const minuteProgressTemplate = slideshow.dataset.loadingMinuteProgressTemplate || 'Starting in :seconds seconds';

    const setStartupStage = (stage, progress = null) => {
        if (startupStatus && loadingStages[stage]) {
            startupStatus.textContent = loadingStages[stage];
        }

        const total = Math.max(0, Number(progress?.total || 0));
        const completed = Math.max(0, Math.min(total, Number(progress?.completed || 0)));
        const percent = total > 0 ? Math.max(0, Math.min(100, (completed / total) * 100)) : 0;
        if (startupProgressBar) {
            startupProgressBar.style.width = `${percent}%`;
        }
        if (startupProgressTrack) {
            startupProgressTrack.setAttribute('aria-valuenow', String(Math.round(percent)));
            startupProgressTrack.setAttribute('aria-label', loadingStages[stage] || stage);
        }
        if (!startupProgress) return;

        const template = stage === 'waitingGroup' ? groupProgressTemplate : progressTemplate;
        startupProgress.textContent = typeof progress?.text === 'string'
            ? progress.text
            : (total > 0
                ? template.replace(':completed', String(completed)).replace(':total', String(total))
                : '');
    };

    const updateServerClock = value => {
        const serverTimeMs = Number(value);
        if (!Number.isFinite(serverTimeMs) || serverTimeMs <= 0) {
            return false;
        }

        serverClockOffsetMs = window.HuginPlaybackScheduler
            ? window.HuginPlaybackScheduler.serverClockOffset(serverTimeMs)
            : serverTimeMs - Date.now();
        return true;
    };

    updateServerClock(slideshow.dataset.serverTimeMs);

    const serverNowMs = () => Date.now() + serverClockOffsetMs;

    const delayUntilServerTime = targetMs => window.HuginPlaybackScheduler
        ? window.HuginPlaybackScheduler.delayUntil(targetMs, serverClockOffsetMs)
        : Math.max(0, Math.ceil(Number(targetMs || 0) - serverNowMs()));

    // Timetable boundaries come from the server because only it has the full
    // assignment set and display timezone. The periodic checks remain a safety
    // net for suspended tabs, clock changes, and edited configuration.
    const queueSelectionBoundaryCheck = value => {
        window.clearTimeout(selectionBoundaryTimer);
        selectionBoundaryTimer = null;
        nextSelectionAtMs = window.HuginPlaybackScheduler
            ? window.HuginPlaybackScheduler.normalizeTimestamp(value)
            : Math.max(0, Number(value || 0));
        if (nextSelectionAtMs <= 0) return;

        selectionBoundaryTimer = window.setTimeout(() => {
            selectionBoundaryTimer = null;
            reloadIfChanged('selection-boundary');
        }, Math.max(25, delayUntilServerTime(nextSelectionAtMs) + 25));
    };

    const sleep = ms => new Promise(resolve => {
        window.setTimeout(resolve, Math.max(0, Math.ceil(Number(ms) || 0)));
    });

    const fetchWithTimeout = (url, options = {}, timeoutMs = CACHE_READINESS_REQUEST_TIMEOUT_MS) => {
        if (!window.fetch) {
            return Promise.reject(new Error('Fetch is unavailable.'));
        }

        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timeout = window.setTimeout(() => controller?.abort(), Math.max(1000, timeoutMs));
        const request = controller
            ? Object.assign({}, options, { signal: controller.signal })
            : options;

        // Promise.race is still required for older embedded browsers without
        // AbortController. A stalled readiness request must never own startup.
        return Promise.race([
            window.fetch(url, request),
            sleep(timeoutMs).then(() => {
                throw new Error('Display request timed out.');
            }),
        ]).finally(() => window.clearTimeout(timeout));
    };

    const padDateTimePart = value => String(Math.max(0, Number(value) || 0)).padStart(2, '0');

    const formatTemplateDateTime = element => {
        const now = new Date(serverNowMs());
        const mode = (element?.dataset.templateDatetimeMode || 'clock').toLowerCase();
        if (mode === 'date') {
            return `${padDateTimePart(now.getDate())}.${padDateTimePart(now.getMonth() + 1)}.${now.getFullYear()}`;
        }

        const minutes = padDateTimePart(now.getMinutes());
        const format = (element?.dataset.templateTimeFormat || '24h').toLowerCase();
        if (format === 'ampm') {
            const hours24 = now.getHours();
            const hours12 = hours24 % 12 || 12;
            return `${padDateTimePart(hours12)}:${minutes} ${hours24 >= 12 ? 'PM' : 'AM'}`;
        }

        return `${padDateTimePart(now.getHours())}:${minutes}`;
    };

    const updateTemplateDateTimeElements = () => {
        const elements = Array.from(document.querySelectorAll('[data-template-datetime]'));
        elements.forEach(element => {
            const target = element.querySelector('.template-slide__datetime-content') || element;
            target.textContent = formatTemplateDateTime(element);
        });
        return elements.length > 0;
    };

    const targetDateTimeMs = value => {
        const raw = String(value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/.test(raw)) {
            return NaN;
        }

        const parsed = new Date(raw).getTime();
        return Number.isFinite(parsed) ? parsed : NaN;
    };

    const formatTemplateCountdownSeconds = totalSeconds => {
        let remaining = Math.max(0, Math.floor(Number(totalSeconds) || 0));
        const days = Math.floor(remaining / 86400);
        remaining %= 86400;
        const hours = Math.floor(remaining / 3600);
        remaining %= 3600;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        return `${padDateTimePart(days)}d ${padDateTimePart(hours)}h ${padDateTimePart(minutes)}m ${padDateTimePart(seconds)}s`;
    };

    const formatTemplateCountdown = element => {
        const targetMsAttr = Number(element?.dataset.templateCountdownTargetMs || NaN);
        if (Number.isFinite(targetMsAttr) && targetMsAttr > 0) {
            return formatTemplateCountdownSeconds((targetMsAttr - serverNowMs()) / 1000);
        }

        const targetMs = targetDateTimeMs(element?.dataset.templateCountdownTarget || '');
        if (!Number.isFinite(targetMs)) {
            return formatTemplateCountdownSeconds(0);
        }

        return formatTemplateCountdownSeconds((targetMs - serverNowMs()) / 1000);
    };

    const updateTemplateCountdownElements = () => {
        const elements = Array.from(document.querySelectorAll('[data-template-countdown]'));
        elements.forEach(element => {
            const target = element.querySelector('.template-slide__countdown-content') || element;
            target.textContent = formatTemplateCountdown(element);
        });
        return elements.length > 0;
    };

    const updateTemplateTimedElements = () => {
        const hasDateTime = updateTemplateDateTimeElements();
        const hasCountdown = updateTemplateCountdownElements();
        return hasDateTime || hasCountdown;
    };

    const dynamicTextCleanups = new WeakMap();

    const templateDynamicTextIntervalMs = element => {
        const intervalMs = Number(element?.dataset.templateDynamicTextIntervalMs || 4000);
        if (!Number.isFinite(intervalMs)) return 4000;
        return Math.max(500, Math.min(60000, Math.round(intervalMs)));
    };

    const templateDynamicTextTransitionMs = element => {
        const transitionMs = Number(element?.dataset.templateDynamicTextTransitionMs || 400);
        if (!Number.isFinite(transitionMs)) return 400;
        return Math.max(0, Math.min(5000, Math.round(transitionMs)));
    };

    const prefersReducedMotion = () => !!window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

    const clearTemplateDynamicTextRuntime = element => {
        const cleanup = dynamicTextCleanups.get(element);
        if (cleanup) {
            cleanup();
            dynamicTextCleanups.delete(element);
        }
    };

    const activeTemplateDynamicTextLineIndex = lines => {
        const index = lines.findIndex(line => line.classList.contains('is-active'));
        if (index >= 0) return index;
        lines[0]?.classList.add('is-active');
        return 0;
    };

    const startTemplateDynamicTextLineRotation = (element, push = false) => {
        const lines = Array.from(element.querySelectorAll('.template-slide__dynamic-text-line'));
        if (lines.length <= 1) return;

        let lineIndex = activeTemplateDynamicTextLineIndex(lines);
        const exitTimers = new Set();
        const clearExitTimers = () => {
            exitTimers.forEach(timer => window.clearTimeout(timer));
            exitTimers.clear();
        };

        // Dynamic Text animation state must remain DOM-local. Do not write line indexes,
        // typed/word positions, timestamps, or transforms to slide data used for reload checks.
        const timer = window.setInterval(() => {
            const previous = lines[lineIndex];
            lineIndex = (lineIndex + 1) % lines.length;
            const next = lines[lineIndex];

            if (push) {
                clearExitTimers();
                previous?.classList.add('is-exiting');
                previous?.classList.remove('is-active');
                next?.classList.add('is-active');
                const exitTimer = window.setTimeout(() => {
                    previous?.classList.remove('is-exiting');
                    exitTimers.delete(exitTimer);
                }, templateDynamicTextTransitionMs(element));
                exitTimers.add(exitTimer);
                return;
            }

            previous?.classList.remove('is-active');
            next?.classList.add('is-active');
        }, templateDynamicTextIntervalMs(element));

        dynamicTextCleanups.set(element, () => {
            window.clearInterval(timer);
            clearExitTimers();
        });
    };

    const startTemplateDynamicTextTypewriter = element => {
        const lines = Array.from(element.querySelectorAll('.template-slide__dynamic-text-line'));
        if (lines.length === 0) return;

        const sourceLines = lines.map(line => line.textContent || '\u00a0');
        const timers = new Set();
        const schedule = (callback, delayMs) => {
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                callback();
            }, Math.max(0, delayMs));
            timers.add(timer);
        };
        let lineIndex = activeTemplateDynamicTextLineIndex(lines);

        dynamicTextCleanups.set(element, () => {
            timers.forEach(timer => window.clearTimeout(timer));
            timers.clear();
        });

        const showLine = () => {
            lines.forEach((line, index) => {
                line.classList.toggle('is-active', index === lineIndex);
                line.textContent = index === lineIndex ? '' : sourceLines[index];
            });

            const activeLine = lines[lineIndex];
            const characters = Array.from(sourceLines[lineIndex] || '\u00a0');
            const transitionMs = templateDynamicTextTransitionMs(element);
            const stepMs = characters.length > 0 && transitionMs > 0 ? transitionMs / characters.length : 0;

            const reveal = characterIndex => {
                activeLine.textContent = characters.slice(0, characterIndex).join('') || '\u00a0';
                if (characterIndex >= characters.length) {
                    schedule(() => {
                        lineIndex = (lineIndex + 1) % lines.length;
                        showLine();
                    }, templateDynamicTextIntervalMs(element));
                    return;
                }
                schedule(() => reveal(characterIndex + 1), stepMs);
            };

            reveal(transitionMs > 0 ? 0 : characters.length);
        };

        showLine();
    };

    const templateDynamicTextWordParts = text => {
        const parts = String(text || '\u00a0').split(/(\s+)/).filter(part => part !== '');
        return parts.length > 0 ? parts.map(part => ({ text: part, word: !/^\s+$/.test(part) })) : [{ text: '\u00a0', word: false }];
    };

    const renderTemplateDynamicTextWords = (line, parts, visibleWords) => {
        line.textContent = '';
        let wordIndex = 0;
        parts.forEach(part => {
            if (!part.word) {
                line.appendChild(document.createTextNode(part.text));
                return;
            }

            wordIndex += 1;
            const word = document.createElement('span');
            word.className = `template-slide__dynamic-text-word ${wordIndex <= visibleWords ? 'is-visible' : ''}`.trim();
            word.textContent = part.text;
            line.appendChild(word);
        });
    };

    const startTemplateDynamicTextWordReveal = element => {
        const lines = Array.from(element.querySelectorAll('.template-slide__dynamic-text-line'));
        if (lines.length === 0) return;

        const sourceLines = lines.map(line => line.textContent || '\u00a0');
        const timers = new Set();
        const schedule = (callback, delayMs) => {
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                callback();
            }, Math.max(0, delayMs));
            timers.add(timer);
        };
        let lineIndex = activeTemplateDynamicTextLineIndex(lines);

        dynamicTextCleanups.set(element, () => {
            timers.forEach(timer => window.clearTimeout(timer));
            timers.clear();
        });

        const showLine = () => {
            lines.forEach((line, index) => {
                line.classList.toggle('is-active', index === lineIndex);
                line.textContent = sourceLines[index];
            });

            const activeLine = lines[lineIndex];
            const parts = templateDynamicTextWordParts(sourceLines[lineIndex]);
            const wordCount = parts.filter(part => part.word).length;
            const transitionMs = templateDynamicTextTransitionMs(element);
            const stepMs = wordCount > 0 && transitionMs > 0 ? transitionMs / wordCount : 0;

            const reveal = visibleWords => {
                renderTemplateDynamicTextWords(activeLine, parts, visibleWords);
                if (visibleWords >= wordCount) {
                    schedule(() => {
                        lineIndex = (lineIndex + 1) % lines.length;
                        showLine();
                    }, templateDynamicTextIntervalMs(element));
                    return;
                }
                schedule(() => reveal(visibleWords + 1), stepMs);
            };

            reveal(transitionMs > 0 ? 0 : wordCount);
        };

        showLine();
    };

    const initializeTemplateDynamicTextElements = () => {
        document.querySelectorAll('[data-template-dynamic-text]').forEach(element => {
            clearTemplateDynamicTextRuntime(element);
            if (prefersReducedMotion()) return;

            const mode = element.dataset.templateDynamicTextMode || 'carousel';
            if (mode === 'carousel') {
                startTemplateDynamicTextLineRotation(element, false);
            } else if (mode === 'push_carousel') {
                startTemplateDynamicTextLineRotation(element, true);
            } else if (mode === 'typewriter') {
                startTemplateDynamicTextTypewriter(element);
            } else if (mode === 'word_reveal') {
                startTemplateDynamicTextWordReveal(element);
            }
        });
    };

    const hasStoredScheduledSyncReload = () => {
        try {
            const raw = window.sessionStorage.getItem(SCHEDULED_SYNC_RELOAD_KEY);
            const data = raw ? JSON.parse(raw) : null;
            const ageMs = Date.now() - Number(data?.at || 0);
            return data?.reason === 'sync-group-config-reload' && ageMs >= 0 && ageMs <= SCHEDULED_SYNC_RELOAD_MAX_AGE_MS;
        } catch (error) {
            return false;
        }
    };

    const readStoredCachedUrls = () => {
        try {
            const raw = window.localStorage.getItem(`hugin:display-cache:${window.location.pathname}`);
            const data = raw ? JSON.parse(raw) : null;
            return Array.isArray(data?.cachedUrls) ? data.cachedUrls : [];
        } catch (error) {
            return [];
        }
    };

    const cachedAssetUrls = new Set(readStoredCachedUrls());
    let offlineCacheWarmPromise = null;
    let lastWarmSignature = '';


    const bindMediaFallback = element => {
        if (!element || element.dataset.fallbackBound) return;
        element.dataset.fallbackBound = '1';
        element.addEventListener('error', () => {
            element.classList.add('is-media-error');
            element.dataset.mediaFailedAt = String(Date.now());
            const failedSlide = element.closest?.('.slide');
            if (!startupComplete || failedSlide !== slides[index]) return;

            const fallbackIndex = nextPlayableIndex(index);
            if (fallbackIndex >= 0 && fallbackIndex !== index) {
                window.setTimeout(() => {
                    if (failedSlide === slides[index]) activate(fallbackIndex);
                }, 100);
                return;
            }

            window.setTimeout(() => {
                if (failedSlide !== slides[index]) return;
                delete element.dataset.mediaFailedAt;
                element.removeAttribute('src');
                ensureMediaLoaded(failedSlide);
                if (element.tagName === 'VIDEO') startVideo(failedSlide);
            }, isProbablyOffline() ? MEDIA_FAILURE_RETRY_MS : 5000);
        });
        const recovered = () => {
            element.classList.remove('is-media-error');
            delete element.dataset.mediaFailedAt;
        };
        element.addEventListener('load', recovered);
        element.addEventListener('canplay', recovered);
    };

    const restartTextCardAnimation = slide => {
        const textSlide = slide?.querySelector('.text-slide[data-text-animation]');
        if (!textSlide) return;

        slide.classList.remove('is-text-card-animating');
        if ((textSlide.dataset.textAnimation || 'none') === 'none') {
            return;
        }

        void textSlide.offsetWidth;
        requestFrame(() => {
            slide.classList.add('is-text-card-animating');
        });
    };

    const restartTemplateElementAnimations = slide => {
        if (!slide?.querySelector('.template-slide__element[class*="template-slide__element--entrance-"]')) return;

        slide.classList.remove('is-template-animating');
        void slide.offsetWidth;
        requestFrame(() => {
            slide.classList.add('is-template-animating');
        });
    };

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

    const normalizeAssetUrl = value => {
        if (!value) return '';
        try {
            const url = new URL(value, window.location.href);
            url.hash = '';
            return url.toString();
        } catch (error) {
            return '';
        }
    };

    const isSameOriginAssetUrl = value => {
        const url = normalizeAssetUrl(value);
        if (!url) return false;
        try {
            return new URL(url).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    };

    const isProbablyOffline = () => navigator.onLine === false;

    const storeCachedUrls = () => {
        try {
            window.localStorage.setItem(`hugin:display-cache:${window.location.pathname}`, JSON.stringify({
                at: Date.now(),
                signature: currentSignature,
                cachedUrls: Array.from(cachedAssetUrls),
            }));
        } catch (error) {}
    };

    const mergeCachedUrls = urls => {
        (Array.isArray(urls) ? urls : []).forEach(url => {
            const normalized = normalizeAssetUrl(url);
            if (normalized) cachedAssetUrls.add(normalized);
        });
        storeCachedUrls();
    };

    const assetUrlsForSlide = slide => {
        if (!slide) return [];
        const urls = [];
        slide.querySelectorAll('img[data-src], video[data-src]').forEach(element => {
            const url = normalizeAssetUrl(element.dataset.src || '');
            if (url) urls.push(url);
        });
        slide.querySelectorAll('.text-slide-background--image[data-bg-src]').forEach(element => {
            const url = normalizeAssetUrl(element.dataset.bgSrc || '');
            if (url) urls.push(url);
        });
        return Array.from(new Set(urls));
    };

    const isSlidePlayable = slide => {
        if (!slide) return false;
        const hasRecentFailure = Array.from(slide.querySelectorAll('[data-media-failed-at]')).some(element => {
            const failedAt = Number(element.dataset.mediaFailedAt || 0);
            return failedAt > 0 && Date.now() - failedAt < MEDIA_FAILURE_RETRY_MS;
        });
        if (hasRecentFailure && slides.some(candidate => candidate !== slide && !candidate.querySelector('[data-media-failed-at]'))) {
            return false;
        }
        if (!isProbablyOffline()) return true;

        const policy = slide.dataset.offlinePolicy || 'skip';
        if (policy === 'skip') return false;
        if (slide.querySelector('iframe[data-src], iframe[src]')) return false;

        if (policy === 'try') {
            return true;
        }

        return assetUrlsForSlide(slide).every(url => !isSameOriginAssetUrl(url) || cachedAssetUrls.has(url));
    };

    const firstPlayableIndex = () => slides.findIndex(slide => isSlidePlayable(slide));

    const nextPlayableIndex = (fromIndex, offset = 1) => {
        if (slides.length === 0) return -1;
        const startOffset = Math.max(0, offset);
        for (let step = startOffset; step < slides.length + startOffset; step += 1) {
            const candidate = nextIndex(fromIndex, step);
            if (isSlidePlayable(slides[candidate])) {
                return candidate;
            }
        }
        return -1;
    };

    const registerDisplayServiceWorker = () => {
        const serviceWorkerUrl = resolveEndpointUrl(slideshow.dataset.serviceWorkerUrl || '');
        if (!serviceWorkerUrl || !('serviceWorker' in navigator)) {
            return Promise.resolve(false);
        }

        const registration = navigator.serviceWorker.register(serviceWorkerUrl, { scope: '/display/' })
            .then(result => result.active ? result : navigator.serviceWorker.ready)
            .catch(() => null);
        const timeout = sleep(10000).then(() => null);
        return Promise.race([registration, timeout]);
    };

    const serviceWorkerReady = registerDisplayServiceWorker();

    const postServiceWorkerMessage = (type, payload = {}, options = {}) => serviceWorkerReady.then(registration => {
        if (!registration || !navigator.serviceWorker) {
            throw new Error('Display service worker is unavailable.');
        }

        return new Promise((resolve, reject) => {
            const worker = registration.active || navigator.serviceWorker.controller;
            if (!worker) {
                reject(new Error('Display service worker is not active.'));
                return;
            }

            const channel = new MessageChannel();
            const idleTimeoutMs = Math.max(15000, Number(options.idleTimeoutMs || 45000));
            let settled = false;
            let timeout = null;
            const finish = callback => value => {
                if (settled) return;
                settled = true;
                window.clearTimeout(timeout);
                callback(value);
            };
            const fail = finish(reject);
            const succeed = finish(resolve);
            const resetTimeout = () => {
                window.clearTimeout(timeout);
                timeout = window.setTimeout(() => fail(new Error('Display service worker timed out.')), idleTimeoutMs);
            };
            resetTimeout();
            channel.port1.onmessage = event => {
                resetTimeout();
                const data = event.data || {};
                if (data.ok === false) {
                    fail(new Error(data.error || 'Display service worker request failed.'));
                    return;
                }
                if (data.type === `${type}_PROGRESS` || data.progress) {
                    if (typeof options.onProgress === 'function') {
                        options.onProgress(data.progress || data);
                    }
                    return;
                }
                succeed(data);
            };
            worker.postMessage(Object.assign({ type }, payload), [channel.port2]);
        });
    });

    const resolveOfflineCacheBudget = () => {
        const hardCap = (navigator.deviceMemory && Number(navigator.deviceMemory) <= 2 ? 1 : 3) * 1024 * 1024 * 1024;
        if (!navigator.storage?.estimate) {
            return Promise.resolve(hardCap);
        }

        return navigator.storage.estimate()
            .then(estimate => {
                const quota = Number(estimate.quota || 0);
                const usage = Number(estimate.usage || 0);
                const availableWithReserve = quota > 0 ? Math.max(0, Math.floor((quota * 0.8) - usage)) : hardCap;
                return Math.max(64 * 1024 * 1024, Math.min(hardCap, availableWithReserve || hardCap));
            })
            .catch(() => hardCap);
    };

    const fetchOfflineManifest = () => {
        const url = resolveEndpointUrl(slideshow.dataset.offlineManifestUrl || '');
        if (!url || !window.fetch) return Promise.resolve(null);

        return fetchWithTimeout(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        }, STATE_REQUEST_TIMEOUT_MS)
            .then(response => response.ok ? response.json() : null)
            .then(data => data?.ok === true ? data : null)
            .catch(() => null);
    };

    const applyManifestSlidePolicies = manifest => {
        const policies = new Map((manifest?.slides || []).map(slide => [String(slide.id), slide]));
        slides.forEach(slide => {
            const item = policies.get(String(slide.dataset.slideId || ''));
            if (!item) return;
            slide.dataset.offlinePolicy = item.policy || slide.dataset.offlinePolicy || 'skip';
            slide.dataset.offlineRequiredAssets = JSON.stringify(item.required_asset_urls || []);
        });
    };

    const offlinePlayableCount = (manifest, cachedUrls) => {
        const cached = new Set(Array.isArray(cachedUrls) ? cachedUrls.map(normalizeAssetUrl).filter(Boolean) : []);
        return (manifest?.slides || []).filter(slide => {
            if ((slide.policy || 'skip') === 'skip') return false;
            if ((slide.policy || '') === 'try') return true;
            return (slide.required_asset_urls || []).every(url => cached.has(normalizeAssetUrl(url)));
        }).length;
    };

    const warmOfflineCache = (reason = 'startup', options = {}) => {
        if (reason === 'state-check' && currentSignature && lastWarmSignature === currentSignature) {
            return Promise.resolve({
                manifest: null,
                offlinePlayableCount: 0,
                cachedUrls: Array.from(cachedAssetUrls),
                cacheStatus: 'ready',
                reason,
            });
        }
        if (offlineCacheWarmPromise) return offlineCacheWarmPromise;

        setStartupStage(reason === 'startup' ? 'preparing' : 'caching');
        offlineCacheWarmPromise = fetchOfflineManifest()
            .then(manifest => {
                if (!manifest) {
                    setStartupStage('degraded', { completed: 1, total: 1 });
                    return {
                        manifest: null,
                        offlinePlayableCount: 0,
                        cachedUrls: Array.from(cachedAssetUrls),
                        cacheStatus: 'degraded',
                        totalAssets: 0,
                        cachedAssets: cachedAssetUrls.size,
                        skippedAssets: 0,
                        bytesReserved: 0,
                        reason,
                    };
                }
                applyManifestSlidePolicies(manifest);
                setStartupStage('caching', { completed: 0, total: Array.isArray(manifest.assets) ? manifest.assets.length : 0 });
                return resolveOfflineCacheBudget()
                    .then(maxBytes => postServiceWorkerMessage('CACHE_DISPLAY_MANIFEST', { manifest, maxBytes }, {
                        idleTimeoutMs: 120000,
                        onProgress: progress => {
                            if (typeof options.onProgress === 'function') {
                                options.onProgress(progress);
                            }
                            setStartupStage('caching', {
                                completed: progress?.completed || 0,
                                total: progress?.total || 0,
                            });
                        },
                    }))
                    .then(result => {
                        mergeCachedUrls(result.cachedUrls || []);
                        lastWarmSignature = manifest.signature || lastWarmSignature;
                        const skippedAssets = Number(result.skippedAssets || 0);
                        const cacheStatus = skippedAssets > 0 ? 'degraded' : 'ready';
                        setStartupStage(cacheStatus === 'ready' ? 'ready' : 'degraded', { completed: 1, total: 1 });
                        return {
                            manifest,
                            offlinePlayableCount: offlinePlayableCount(manifest, result.cachedUrls || []),
                            cachedUrls: result.cachedUrls || [],
                            cacheStatus,
                            totalAssets: Number(result.totalAssets || manifest.assets?.length || 0),
                            cachedAssets: Number(result.cachedAssets || (result.cachedUrls || []).length),
                            skippedAssets,
                            bytesReserved: Number(result.bytesReserved || 0),
                            reason,
                        };
                    })
                    .catch(error => {
                        setStartupStage('degraded', { completed: 1, total: 1 });
                        return {
                            manifest,
                            offlinePlayableCount: offlinePlayableCount(manifest, Array.from(cachedAssetUrls)),
                            cachedUrls: Array.from(cachedAssetUrls),
                            cacheStatus: 'degraded',
                            totalAssets: Array.isArray(manifest.assets) ? manifest.assets.length : 0,
                            cachedAssets: cachedAssetUrls.size,
                            skippedAssets: 0,
                            bytesReserved: 0,
                            reason,
                            error: String(error?.message || error),
                        };
                    });
            })
            .then(result => {
                offlineCacheWarmPromise = null;
                return result;
            })
            .catch(error => {
                offlineCacheWarmPromise = null;
                setStartupStage('degraded', { completed: 1, total: 1 });
                return {
                    manifest: null,
                    offlinePlayableCount: 0,
                    cachedUrls: Array.from(cachedAssetUrls),
                    cacheStatus: 'degraded',
                    totalAssets: 0,
                    cachedAssets: cachedAssetUrls.size,
                    skippedAssets: 0,
                    bytesReserved: 0,
                    reason,
                    error: String(error?.message || error),
                };
            });

        return offlineCacheWarmPromise;
    };

    const prepareOfflineCacheForReload = stateData => warmOfflineCache('config-reload')
        .then(result => ({ defer: false, result }))
        .catch(() => ({ defer: false, result: null, stateData }));

    const runWhenOfflineReady = (stateData, applyReload) => {
        prepareOfflineCacheForReload(stateData).then(({ defer }) => {
            if (defer) {
                logReload('Deferred reload because the new playlist has no offline-playable slides cached yet', {
                    signature: stateData?.signature || '',
                    displayGroup: displayGroupFromState(stateData),
                });
                return;
            }
            applyReload();
        }).catch(applyReload);
    };

    const cacheReadinessUrl = () => resolveEndpointUrl(slideshow.dataset.cacheReadinessUrl || '');

    const cacheReadinessPayload = (reason, cacheResult = {}) => {
        const signature = cacheResult?.manifest?.signature || cacheResult?.stateSignature || currentSignature || '';
        return {
            reason,
            state_signature: signature,
            manifest_signature: cacheResult?.manifest?.signature || signature,
            cache_status: cacheResult?.cacheStatus === 'ready' ? 'ready' : 'degraded',
            total_assets: Math.max(0, Number(cacheResult?.totalAssets || 0)),
            cached_assets: Math.max(0, Number(cacheResult?.cachedAssets || 0)),
            skipped_assets: Math.max(0, Number(cacheResult?.skippedAssets || 0)),
            bytes_reserved: Math.max(0, Number(cacheResult?.bytesReserved || 0)),
        };
    };

    const normalizeReadinessStatus = data => {
        if (data?.server_time_ms) {
            updateServerClock(data.server_time_ms);
        }
        return {
            ok: data?.ok === true,
            syncEnabled: data?.sync_enabled === true,
            released: data?.released === true,
            startAtMs: Number(data?.start_at_ms || 0),
            participantCount: Math.max(0, Number(data?.participant_count || 0)),
            readyCount: Math.max(0, Number(data?.ready_count || 0)),
            pendingCount: Math.max(0, Number(data?.pending_count || 0)),
            generationHash: data?.generation_hash || '',
            raw: data || {},
        };
    };

    const postCacheReadiness = (reason, cacheResult = {}) => {
        const url = cacheReadinessUrl();
        if (!url || !window.fetch) {
            return Promise.resolve(normalizeReadinessStatus({ ok: true, released: true }));
        }

        return fetchWithTimeout(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(cacheReadinessPayload(reason, cacheResult)),
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then(response => response.ok ? response.json() : null)
            .then(data => normalizeReadinessStatus(data || { ok: false, released: true }));
    };

    const fetchCacheReadinessStatus = () => {
        const url = cacheReadinessUrl();
        if (!url || !window.fetch) {
            return Promise.resolve(normalizeReadinessStatus({ ok: true, released: true }));
        }

        return fetchWithTimeout(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then(response => response.ok ? response.json() : null)
            .then(data => normalizeReadinessStatus(data || { ok: false, released: true }));
    };

    const failOpenReadinessStatus = () => normalizeReadinessStatus({ ok: false, released: true });

    const showReadinessGroupStage = status => {
        setStartupStage('waitingGroup', {
            completed: status?.readyCount || 0,
            total: status?.participantCount || 0,
        });
    };

    const showStartupMinuteProgress = startAtMs => {
        const target = Number(startAtMs || 0);
        const remainingMs = Math.max(0, target - serverNowMs());
        // Releases are aligned to a minute. Using the preceding minute as the
        // progress origin makes the final synchronization phase comparable on
        // every display, even when a client learns about the release late.
        const totalMs = MINUTE_MS;
        const completedMs = Math.max(0, totalMs - Math.min(totalMs, remainingMs));
        setStartupStage('waitingMinute', {
            completed: completedMs,
            total: totalMs,
            text: minuteProgressTemplate.replace(':seconds', String(Math.ceil(remainingMs / 1000))),
        });
    };

    const waitForStartupMinute = startAtMs => new Promise(resolve => {
        const target = Number(startAtMs || 0);
        const update = () => showStartupMinuteProgress(target);
        update();
        const progressTimer = window.setInterval(update, 250);
        window.setTimeout(() => {
            window.clearInterval(progressTimer);
            showStartupMinuteProgress(target);
            resolve();
        }, delayUntilServerTime(target));
    });

    const readinessTimeoutFallback = status => Object.assign({}, status || {}, {
        ok: false,
        released: true,
        startAtMs: computeNextFullMinuteActivation(),
    });

    const waitForCacheReadinessReleaseStatus = initialStatus => {
        const waitStartedAt = Date.now();
        const poll = currentStatus => {
            if (!shouldUseSyncedGroupReload() || !currentStatus?.syncEnabled || currentStatus.ok === false) {
                return currentStatus;
            }

            if (currentStatus.released && (currentStatus.startAtMs > 0 || currentStatus.participantCount <= 0)) {
                return currentStatus;
            }

            if (Date.now() - waitStartedAt >= CACHE_READINESS_MAX_WAIT_MS) {
                logSyncDebug('cache readiness wait timed out; using full-minute fallback', {
                    participantCount: currentStatus?.participantCount || 0,
                    readyCount: currentStatus?.readyCount || 0,
                });
                return readinessTimeoutFallback(currentStatus);
            }

            showReadinessGroupStage(currentStatus);

            return sleep(CACHE_READINESS_POLL_MS)
                .then(fetchCacheReadinessStatus)
                .then(nextStatus => nextStatus.ok === false ? nextStatus : poll(nextStatus));
        };

        return poll(initialStatus);
    };

    const waitForCacheReadinessRelease = (reason, cacheResult = {}) => postCacheReadiness(reason, cacheResult)
        .then(waitForCacheReadinessReleaseStatus)
        .catch(error => {
            logSyncDebug('cache readiness coordination failed open', {
                reason,
                error: String(error?.message || error),
            });
            return failOpenReadinessStatus();
        });

    const readinessStatusWithFallbackStart = (status, fallbackStartAtMs = 0) => {
        const nextStatus = Object.assign({}, status || {});
        if (!nextStatus.released || Number(nextStatus.startAtMs || 0) > serverNowMs()) {
            return nextStatus;
        }

        const fallback = Number(fallbackStartAtMs || 0);
        nextStatus.startAtMs = fallback > serverNowMs()
            ? fallback
            : computeNextFullMinuteActivation();
        return nextStatus;
    };

    const releaseStillCoversStart = (latestStatus, expectedStatus) => {
        if (!latestStatus?.syncEnabled || latestStatus.ok === false) {
            return true;
        }
        if (!latestStatus.released) {
            return false;
        }

        const latestStartAtMs = Number(latestStatus.startAtMs || 0);
        const expectedStartAtMs = Number(expectedStatus?.startAtMs || 0);
        return latestStartAtMs > 0 && latestStartAtMs <= expectedStartAtMs;
    };

    const waitForReadinessStart = (status, options = {}) => {
        const firstStatus = readinessStatusWithFallbackStart(status, options?.fallbackStartAtMs || 0);
        const waitStartedAt = Date.now();

        const poll = currentStatus => {
            if (!shouldUseSyncedGroupReload() || !currentStatus?.syncEnabled || currentStatus.ok === false) {
                const startAtMs = Number(currentStatus?.startAtMs || 0);
                if (startAtMs > serverNowMs()) {
                    return waitForStartupMinute(startAtMs);
                }
                return Promise.resolve(currentStatus);
            }

            if (!currentStatus.released || Number(currentStatus.startAtMs || 0) <= 0) {
                if (Date.now() - waitStartedAt >= CACHE_READINESS_MAX_WAIT_MS) {
                    return poll(readinessTimeoutFallback(currentStatus));
                }
                showReadinessGroupStage(currentStatus);
                return sleep(CACHE_READINESS_POLL_MS)
                    .then(fetchCacheReadinessStatus)
                    .then(nextStatus => nextStatus.ok === false ? nextStatus : poll(nextStatus))
                    .catch(failOpenReadinessStatus);
            }

            const startAtMs = Number(currentStatus.startAtMs || 0);
            if (startAtMs <= serverNowMs()) {
                return Promise.resolve(currentStatus);
            }

            showStartupMinuteProgress(startAtMs);
            return sleep(Math.min(delayUntilServerTime(startAtMs), CACHE_READINESS_POLL_MS))
                .then(fetchCacheReadinessStatus)
                .then(nextStatus => {
                    if (nextStatus.ok === false) {
                        return serverNowMs() >= startAtMs ? currentStatus : poll(currentStatus);
                    }
                    if (serverNowMs() >= startAtMs && releaseStillCoversStart(nextStatus, currentStatus)) {
                        return nextStatus;
                    }
                    return poll(nextStatus);
                })
                .catch(() => serverNowMs() >= startAtMs ? currentStatus : poll(currentStatus));
        };

        return poll(firstStatus);
    };


    const stateCheckIntervalMs = () => {
        const seconds = parseInt(slideshow.dataset.stateCheckInterval || '60', 10);
        return Math.max(seconds || 60, 5) * 1000;
    };

    const durationForSlide = slide => {
        const seconds = parseInt(slide?.dataset.duration || slideshow.dataset.defaultDuration || '8', 10);
        return Math.max(seconds || 8, 1) * 1000;
    };

    const nextIndex = (fromIndex, offset = 1) => (fromIndex + offset + slides.length) % slides.length;

    const logReload = (message, context = {}) => {
        if (window.console?.info) {
            window.console.info(`[Hugin display] ${message}`, context);
        }
    };

    const displayGroupFromDataset = () => ({
        id: slideshow.dataset.displayGroupId || '',
        name: slideshow.dataset.displayGroupName || '',
        sync_enabled: slideshow.dataset.syncReloadToFullMinute === '1' ? 1 : 0,
        sync_mode: slideshow.dataset.displayGroupSyncMode || 'independent',
        sync_reload_to_full_minute: slideshow.dataset.syncReloadToFullMinute === '1',
    });

    const displayGroupFromState = stateData => {
        const group = stateData?.display_group || null;
        if (!group) return displayGroupFromDataset();

        return {
            id: group.id || '',
            name: group.name || '',
            sync_enabled: group.sync_enabled ? 1 : 0,
            sync_mode: group.sync_mode || 'independent',
            sync_reload_to_full_minute: Boolean(group.sync_reload_to_full_minute),
        };
    };

    const scheduledSyncReloadForDebug = () => {
        try {
            const raw = window.sessionStorage.getItem(SCHEDULED_SYNC_RELOAD_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return { error: String(error?.message || error) };
        }
    };

    const logSyncDebug = (message, context = {}) => {
        if (!window.console?.info) return;

        const clientNowMs = Date.now();
        const currentServerNowMs = serverNowMs();
        window.console.info(`[Hugin sync debug] ${message}`, Object.assign({
            displayPath: window.location.pathname,
            group: displayGroupFromDataset(),
            currentSignature,
            startupComplete,
            stateRequestInFlight,
            clientNow: new Date(clientNowMs).toISOString(),
            serverNow: new Date(currentServerNowMs).toISOString(),
            serverClockOffsetMs: Math.round(serverClockOffsetMs),
            serverMsIntoMinute: Math.round(currentServerNowMs % MINUTE_MS),
            serverMsUntilMinute: Math.round(MINUTE_MS - (currentServerNowMs % MINUTE_MS)),
            pendingReload: pendingReload ? {
                reason: pendingReload.reason,
                signature: pendingReload.signature || '',
                activateAt: new Date(pendingReload.activateAtMs).toISOString(),
                startAt: pendingReload.startAtMs ? new Date(pendingReload.startAtMs).toISOString() : '',
                msUntilActivate: delayUntilServerTime(pendingReload.activateAtMs),
            } : null,
            storedScheduledReload: scheduledSyncReloadForDebug(),
        }, context));
    };

    logSyncDebug('boot', {
        slideCount: slides.length,
        stateUrl: slideshow.dataset.stateUrl || '',
        startupSyncKey: slideshow.dataset.startupSyncKey || '',
        initialServerTimeMs: slideshow.dataset.serverTimeMs || '',
        startupLoadingSuppressed: document.documentElement.classList.contains('hugin-startup-loading-seen'),
    });

    const shouldUseSyncedGroupReload = stateData => displayGroupFromState(stateData).sync_reload_to_full_minute === true;

    const computeNextFullMinuteActivation = (nowMs = serverNowMs(), minLeadMs = SYNC_RELOAD_MIN_LEAD_MS) => {
        const msIntoMinute = nowMs % MINUTE_MS;
        const nextMinute = msIntoMinute === 0 ? nowMs + MINUTE_MS : nowMs + (MINUTE_MS - msIntoMinute);

        if (nextMinute - nowMs < minLeadMs) {
            return nextMinute + MINUTE_MS;
        }

        return nextMinute;
    };

    const readScheduledSyncReload = () => {
        try {
            const raw = window.sessionStorage.getItem(SCHEDULED_SYNC_RELOAD_KEY);
            const data = raw ? JSON.parse(raw) : null;
            const ageMs = Date.now() - Number(data?.at || 0);

            if (data?.reason === 'sync-group-config-reload' && ageMs >= 0 && ageMs <= SCHEDULED_SYNC_RELOAD_MAX_AGE_MS) {
                logSyncDebug('read scheduled reload marker', {
                    ageMs,
                    activateAt: data.activateAtMs ? new Date(Number(data.activateAtMs)).toISOString() : '',
                    startAt: data.startAtMs ? new Date(Number(data.startAtMs)).toISOString() : '',
                });
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
                return data;
            }

            if (raw) {
                logSyncDebug('discarded stale scheduled reload marker', {
                    ageMs,
                    data,
                });
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
            }
        } catch (error) {
            logSyncDebug('failed to read scheduled reload marker', {
                error: String(error?.message || error),
            });
            try {
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
            } catch (storageError) {}
        }

        return null;
    };

    const markScheduledSyncReload = reload => {
        const startAtMs = Number(reload?.startAtMs || 0)
            || (reload?.activateAtMs ? computeNextFullMinuteActivation(Number(reload.activateAtMs) + 1) : 0);
        try {
            window.sessionStorage.setItem(SCHEDULED_SYNC_RELOAD_KEY, JSON.stringify({
                at: Date.now(),
                activateAtMs: reload?.activateAtMs || 0,
                startAtMs,
                reason: 'sync-group-config-reload',
            }));
        } catch (error) {}
        logSyncDebug('stored scheduled reload marker', {
            reloadReason: reload?.reason || '',
            activateAt: reload?.activateAtMs ? new Date(reload.activateAtMs).toISOString() : '',
            startAt: startAtMs ? new Date(startAtMs).toISOString() : '',
        });
    };

    const reloadImmediately = (reason, stateData = null) => {
        if (pendingReloadTimer) {
            clearTimeout(pendingReloadTimer);
        }
        pendingReloadTimer = null;
        pendingReload = null;

        logSyncDebug('reload immediately requested', {
            reason,
            stateSignature: stateData?.signature || '',
            stateGroup: displayGroupFromState(stateData),
        });

        runWhenOfflineReady(stateData, () => {
            logReload('Applying immediate reload', {
                reason,
                displayGroup: displayGroupFromState(stateData),
                signature: stateData?.signature || '',
            });
            logSyncDebug('applying immediate reload', {
                reason,
                stateSignature: stateData?.signature || '',
            });
            window.location.reload();
        });
    };

    const schedulePendingReload = (reload, status = null, replaced = false) => {
        const statusStartAtMs = Number(status?.startAtMs || 0);
        const startAtMs = statusStartAtMs > serverNowMs()
            ? statusStartAtMs
            : computeNextFullMinuteActivation();
        const activateAtMs = Math.max(serverNowMs(), startAtMs - SYNC_RELOAD_PAGE_LOAD_LEAD_MS);

        pendingReload = Object.assign({}, reload, {
            activateAtMs,
            startAtMs,
            readinessGenerationHash: status?.generationHash || reload.readinessGenerationHash || '',
        });
        pendingReloadTimer = window.setTimeout(applyPendingReload, delayUntilServerTime(activateAtMs));

        logReload(replaced ? 'Replaced pending synchronized reload' : 'Scheduled synchronized reload', {
            reason: pendingReload.reason,
            displayGroup: pendingReload.displayGroup,
            signature: pendingReload.signature,
            activateAt: new Date(activateAtMs).toISOString(),
            startAt: new Date(startAtMs).toISOString(),
        });
        logSyncDebug(replaced ? 'replaced pending synchronized reload' : 'scheduled synchronized reload', {
            reason: pendingReload.reason,
            signature: pendingReload.signature,
            displayGroup: pendingReload.displayGroup,
            activateAt: new Date(activateAtMs).toISOString(),
            startAt: new Date(startAtMs).toISOString(),
            generationHash: pendingReload.readinessGenerationHash,
            msUntilActivate: delayUntilServerTime(activateAtMs),
        });
    };

    const applyPendingReloadNow = reload => {
        logReload('Applying synchronized reload', {
            reason: reload.reason,
            displayGroup: reload.displayGroup,
            signature: reload.signature,
            activateAt: new Date(reload.activateAtMs).toISOString(),
        });
        logSyncDebug('applying synchronized reload', {
            reason: reload.reason,
            signature: reload.signature,
            activateAt: new Date(reload.activateAtMs).toISOString(),
            startAt: reload.startAtMs ? new Date(reload.startAtMs).toISOString() : '',
            generationHash: reload.readinessGenerationHash || '',
        });

        markScheduledSyncReload(reload);
        window.location.reload();
    };

    const applyPendingReload = () => {
        if (!pendingReload) return;

        const reload = pendingReload;
        pendingReload = null;
        pendingReloadTimer = null;

        if (isProbablyOffline()) {
            reload.startAtMs = computeNextFullMinuteActivation();
            reload.activateAtMs = Math.max(serverNowMs(), reload.startAtMs - SYNC_RELOAD_PAGE_LOAD_LEAD_MS);
            pendingReload = reload;
            pendingReloadTimer = window.setTimeout(applyPendingReload, delayUntilServerTime(reload.activateAtMs));
            logReload('Postponed synchronized reload while offline', {
                reason: reload.reason,
                displayGroup: reload.displayGroup,
                signature: reload.signature,
                activateAt: new Date(reload.activateAtMs).toISOString(),
                startAt: new Date(reload.startAtMs).toISOString(),
            });
            logSyncDebug('postponed synchronized reload while offline', {
                reason: reload.reason,
                signature: reload.signature,
                activateAt: new Date(reload.activateAtMs).toISOString(),
                startAt: new Date(reload.startAtMs).toISOString(),
                msUntilActivate: delayUntilServerTime(reload.activateAtMs),
            });
            return;
        }

        if (!shouldUseSyncedGroupReload(reload.stateData)) {
            applyPendingReloadNow(reload);
            return;
        }

        fetchCacheReadinessStatus()
            .then(status => {
                if (status.ok === false || !status.syncEnabled) {
                    applyPendingReloadNow(reload);
                    return;
                }

                if (!status.released || Number(status.startAtMs || 0) <= 0) {
                    logSyncDebug('synchronized reload release revoked before page load', {
                        reason: reload.reason,
                        signature: reload.signature,
                        participantCount: status.participantCount,
                        readyCount: status.readyCount,
                        generationHash: status.generationHash,
                    });
                    return waitForCacheReadinessReleaseStatus(status)
                        .then(nextStatus => {
                            if (nextStatus.ok === false || !nextStatus.syncEnabled) {
                                applyPendingReloadNow(reload);
                                return;
                            }
                            schedulePendingReload(reload, nextStatus, true);
                        })
                        .catch(error => {
                            logSyncDebug('synchronized reload readiness revalidation failed open', {
                                reason: reload.reason,
                                signature: reload.signature,
                                error: String(error?.message || error),
                            });
                            applyPendingReloadNow(reload);
                        });
                }

                const startAtMs = Number(status.startAtMs || 0);
                if (startAtMs - serverNowMs() > SYNC_RELOAD_PAGE_LOAD_LEAD_MS + Math.floor(CACHE_READINESS_POLL_MS / 2)) {
                    schedulePendingReload(reload, status, true);
                    return;
                }

                reload.startAtMs = startAtMs || reload.startAtMs;
                reload.readinessGenerationHash = status.generationHash || reload.readinessGenerationHash || '';
                applyPendingReloadNow(reload);
            })
            .catch(error => {
                logSyncDebug('synchronized reload readiness check failed open', {
                    reason: reload.reason,
                    signature: reload.signature,
                    error: String(error?.message || error),
                });
                applyPendingReloadNow(reload);
            });
    };

    // A signature change identifies a new server-selected playback generation.
    // Sync groups cache/report that generation before the coordinator grants a
    // shared full-minute start; independent displays reload without that gate.
    const scheduleSyncedReload = (reason, stateData) => {
        const signature = stateData?.signature || '';
        const displayGroup = displayGroupFromState(stateData);

        if (pendingReload?.signature === signature && pendingReloadTimer) {
            logReload('Synchronized reload already pending', {
                reason,
                displayGroup,
                signature,
                activateAt: new Date(pendingReload.activateAtMs).toISOString(),
            });
            logSyncDebug('synchronized reload already pending', {
                reason,
                signature,
                activateAt: new Date(pendingReload.activateAtMs).toISOString(),
            });
            return;
        }

        updateServerClock(stateData?.server_time_ms);
        const replaced = Boolean(pendingReloadTimer);
        if (pendingReloadTimer) {
            clearTimeout(pendingReloadTimer);
        }

        const scheduleReload = status => {
            schedulePendingReload({
                reason,
                stateData,
                signature,
                displayGroup,
                stateServerTimeMs: stateData?.server_time_ms || null,
            }, status, replaced);
        };

        warmOfflineCache('config-reload')
            .then(cacheResult => waitForCacheReadinessRelease('config-reload', Object.assign({ stateSignature: signature }, cacheResult)))
            .then(scheduleReload)
            .catch(error => {
                logSyncDebug('synchronized reload cache readiness failed open', {
                    reason,
                    signature,
                    error: String(error?.message || error),
                });
                scheduleReload(null);
            });
    };

    const renderTextSlideQrCodes = () => {
        document.querySelectorAll('[data-qr-url]').forEach(qr => {
            const canvas = qr.querySelector('canvas');
            const qrUrl = qr.dataset.qrUrl || '';
            if (!canvas || !qrUrl) return;

            try {
                window.HuginQr.drawCanvas(canvas, qrUrl, qr.dataset.qrForeground, qr.dataset.qrBackground);
                qr.classList.remove('is-qr-fallback');
            } catch (error) {
                qr.classList.add('is-qr-fallback');
            }
        });
    };

    const msUntilNextMinuteTick = () => {
        const msIntoMinute = serverNowMs() % MINUTE_MS;
        const targetMs = 50;

        if (msIntoMinute < targetMs) {
            return Math.ceil(targetMs - msIntoMinute);
        }

        return Math.ceil(MINUTE_MS - msIntoMinute + targetMs);
    };

    const startupSyncTargetMs = () => {
        const scheduledReload = readScheduledSyncReload();
        if (scheduledReload) {
            const startAtMs = Number(scheduledReload.startAtMs || 0);
            if (Number.isFinite(startAtMs) && startAtMs > serverNowMs()) {
                logReload('Waiting for synchronized playlist start after scheduled reload', {
                    activateAt: scheduledReload.activateAtMs ? new Date(Number(scheduledReload.activateAtMs)).toISOString() : '',
                    startAt: new Date(startAtMs).toISOString(),
                });
                return startAtMs;
            }

            const fallbackStartAtMs = computeNextFullMinuteActivation();
            logReload('Waiting for next minute after scheduled synchronized reload', {
                startAt: new Date(fallbackStartAtMs).toISOString(),
            });
            logSyncDebug('scheduled reload marker had no future start target; using fallback', {
                storedStartAtMs: scheduledReload.startAtMs || 0,
                fallbackStartAt: new Date(fallbackStartAtMs).toISOString(),
            });
            return fallbackStartAtMs;
        }

        if (!shouldUseSyncedGroupReload()) {
            logSyncDebug('startup sync disabled for this display/group');
            return 0;
        }

        const targetMs = computeNextFullMinuteActivation();
        logSyncDebug('startup sync target selected', {
            startAt: new Date(targetMs).toISOString(),
            msUntilStart: delayUntilServerTime(targetMs),
        });
        return targetMs;
    };

    const refreshStartupServerClock = () => {
        if (hasStoredScheduledSyncReload() || !shouldUseSyncedGroupReload()) {
            logSyncDebug('startup server clock refresh skipped', {
                hasStoredScheduledSyncReload: hasStoredScheduledSyncReload(),
                syncedGroup: shouldUseSyncedGroupReload(),
            });
            return Promise.resolve();
        }

        const url = resolveEndpointUrl(slideshow.dataset.stateUrl);
        if (!url || !window.fetch) {
            logSyncDebug('startup server clock refresh unavailable', {
                hasUrl: Boolean(url),
                hasFetch: Boolean(window.fetch),
            });
            return Promise.resolve();
        }

        logSyncDebug('startup server clock refresh request', { url });
        return fetchWithTimeout(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        }, STATE_REQUEST_TIMEOUT_MS)
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                const updated = updateServerClock(data?.server_time_ms);
                logSyncDebug('startup server clock refresh response', {
                    ok: data?.ok ?? null,
                    signature: data?.signature || '',
                    serverTimeMs: data?.server_time_ms || null,
                    updated,
                });
            })
            .catch(error => {
                logSyncDebug('startup server clock refresh failed', {
                    error: String(error?.message || error),
                });
            });
    };

    const shouldWaitForLegacyStartupSync = () => {
        const key = slideshow.dataset.startupSyncKey || `hugin:slideshow-started:${window.location.pathname}`;

        try {
            if (window.sessionStorage.getItem(key)) {
                return false;
            }
            window.sessionStorage.setItem(key, String(Date.now()));
            return true;
        } catch (error) {
            const navEntry = window.performance?.getEntriesByType?.('navigation')?.[0];
            const isReload = navEntry?.type === 'reload' || window.performance?.navigation?.type === 1;
            return !isReload;
        }
    };

    const markStartupSeen = () => {
        const key = slideshow.dataset.startupSyncKey || `hugin:slideshow-started:${window.location.pathname}`;

        try {
            window.sessionStorage.setItem(key, String(Date.now()));
            logSyncDebug('startup seen marker stored', { key });
        } catch (error) {
            logSyncDebug('startup seen marker failed', {
                key,
                error: String(error?.message || error),
            });
        }
    };

    const waitForStartupSync = () => {
        logSyncDebug('waitForStartupSync begin');
        return refreshStartupServerClock().then(() => {
            const targetMs = startupSyncTargetMs();
            if (targetMs > serverNowMs()) {
                logSyncDebug('waiting for startup sync target', {
                    startAt: new Date(targetMs).toISOString(),
                    msUntilStart: delayUntilServerTime(targetMs),
                });
                return shouldUseSyncedGroupReload()
                    ? waitForStartupMinute(targetMs)
                    : sleep(delayUntilServerTime(targetMs));
            }

            if (!shouldWaitForLegacyStartupSync()) {
                logSyncDebug('startup sync skipped by legacy session marker');
                return Promise.resolve();
            }

            const waitMs = msUntilNextMinuteTick();
            logSyncDebug('waiting for legacy startup minute tick', {
                waitMs,
            });
            return new Promise(resolve => {
                window.setTimeout(resolve, waitMs);
            });
        });
    };

    const ensureMediaLoaded = slide => {
        if (!slide || !isSlidePlayable(slide)) return;

        slide.querySelectorAll('.text-slide-background--image[data-bg-src]').forEach(element => {
            const source = element.dataset.bgSrc;
            const normalized = normalizeAssetUrl(source || '');
            if (!source || element.style.backgroundImage) return;
            if (isProbablyOffline() && isSameOriginAssetUrl(normalized) && !cachedAssetUrls.has(normalized)) {
                element.classList.add('is-media-error');
                return;
            }
            element.classList.remove('is-media-error');
            element.style.backgroundImage = `url(${JSON.stringify(source)})`;
        });

        slide.querySelectorAll('img[data-src], video[data-src], iframe[data-src]').forEach(element => {
            const source = element.dataset.src;
            const normalized = normalizeAssetUrl(source || '');
            if (!source || element.getAttribute('src')) return;
            if (element.tagName === 'IFRAME' && isProbablyOffline()) return;
            if (isProbablyOffline() && isSameOriginAssetUrl(normalized) && !cachedAssetUrls.has(normalized)) {
                element.classList.add('is-media-error');
                return;
            }

            bindMediaFallback(element);
            element.classList.remove('is-media-error');
            element.setAttribute('src', source);
            if (element.tagName === 'VIDEO') {
                element.load();
            }
        });
    };

    const waitForSlideImages = (slide, timeoutMs = 5000) => {
        if (!slide) return Promise.resolve();

        const images = Array.from(slide.querySelectorAll('img[data-src]'));
        if (images.length === 0) return Promise.resolve();

        ensureMediaLoaded(slide);
        const pending = new Set(images.filter(image => !image.complete));
        if (pending.size === 0) return Promise.resolve();

        return new Promise(resolve => {
            let settled = false;
            const listeners = new Map();
            const finish = () => {
                if (settled) return;
                settled = true;
                window.clearTimeout(timeout);
                listeners.forEach((listener, image) => {
                    image.removeEventListener('load', listener);
                    image.removeEventListener('error', listener);
                });
                resolve();
            };
            const timeout = window.setTimeout(finish, Math.max(0, timeoutMs));
            pending.forEach(image => {
                const complete = () => {
                    pending.delete(image);
                    if (pending.size === 0) finish();
                };
                listeners.set(image, complete);
                image.addEventListener('load', complete, { once: true });
                image.addEventListener('error', complete, { once: true });
                if (image.complete) complete();
            });
        });
    };

    const unloadHeavyMedia = slide => {
        if (!slide) return;

        slide.querySelectorAll('video[data-src]').forEach(video => {
            clearPendingVideoStart(video);
            video.pause();
            video.removeAttribute('src');
            video.load();
        });

        slide.querySelectorAll('iframe[data-src]').forEach(iframe => {
            iframe.removeAttribute('src');
        });

        slide.querySelectorAll('.text-slide-background--image[data-bg-src]').forEach(element => {
            element.style.backgroundImage = '';
        });
    };

    const prepareMediaAround = activeIndex => {
        ensureMediaLoaded(slides[activeIndex]);
        if (slides.length > 1) {
            const nextPlayable = nextPlayableIndex(activeIndex);
            if (nextPlayable >= 0 && nextPlayable !== activeIndex) {
                ensureMediaLoaded(slides[nextPlayable]);
            }
        }
    };

    const cleanupFarMedia = activeIndex => {
        const keep = new Set([activeIndex]);
        if (slides.length > 1) {
            const nextPlayable = nextPlayableIndex(activeIndex);
            if (nextPlayable >= 0) {
                keep.add(nextPlayable);
            }
        }

        slides.forEach((slide, slideIndex) => {
            if (!keep.has(slideIndex)) {
                unloadHeavyMedia(slide);
            }
        });
    };

    const clearPendingVideoStart = video => {
        const startTimer = videoStartTimers.get(video);
        if (startTimer) {
            window.clearTimeout(startTimer);
            videoStartTimers.delete(video);
        }

        const startHandler = videoStartHandlers.get(video);
        if (startHandler) {
            video.removeEventListener('loadedmetadata', startHandler);
            video.removeEventListener('canplay', startHandler);
            videoStartHandlers.delete(video);
        }
    };

    const resetVideoPlaybackPosition = video => {
        try {
            if (video.readyState > 0) {
                video.currentTime = 0;
            }
        } catch (error) {}
    };

    const playVideoElement = video => {
        try {
            const playPromise = video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {});
            }
        } catch (error) {}
    };

    const stopVideo = slide => {
        slide?.querySelectorAll('video').forEach(video => {
            clearPendingVideoStart(video);
            video.pause();
            resetVideoPlaybackPosition(video);
        });
    };

    const cssTimeToMs = value => {
        const text = String(value || '').trim();
        const match = text.match(/^(-?\d*\.?\d+)(ms|s)?$/i);
        if (!match) return 0;

        const amount = Number(match[1]);
        if (!Number.isFinite(amount) || amount <= 0) return 0;

        return match[2]?.toLowerCase() === 's' ? amount * 1000 : amount;
    };

    const templateVideoStartDelay = video => {
        const element = video.closest('.template-slide__element');
        if (!element || !element.matches('[class*="template-slide__element--entrance-"]')) {
            return 0;
        }

        const delayMs = Number(element.dataset.templateEntranceDelayMs);
        if (Number.isFinite(delayMs) && delayMs > 0) {
            return delayMs;
        }

        return cssTimeToMs(window.getComputedStyle(element).getPropertyValue('--template-entrance-delay'));
    };

    const playVideoFromStart = video => {
        clearPendingVideoStart(video);

        const startPlayback = () => {
            const startHandler = videoStartHandlers.get(video);
            if (startHandler) {
                video.removeEventListener('loadedmetadata', startHandler);
                video.removeEventListener('canplay', startHandler);
                videoStartHandlers.delete(video);
            }

            resetVideoPlaybackPosition(video);
            playVideoElement(video);
        };

        if (video.readyState > 0) {
            startPlayback();
            return;
        }

        const startWhenReady = () => {
            if (videoStartHandlers.get(video) !== startWhenReady) return;
            startPlayback();
        };

        videoStartHandlers.set(video, startWhenReady);
        video.addEventListener('loadedmetadata', startWhenReady);
        video.addEventListener('canplay', startWhenReady);
        playVideoElement(video);
    };

    const startVideo = slide => {
        if (!slide || !isSlidePlayable(slide)) return;

        ensureMediaLoaded(slide);
        slide.querySelectorAll('video').forEach(video => {
            clearPendingVideoStart(video);

            const templateElement = video.closest('.template-slide__element');
            if (!templateElement) {
                playVideoFromStart(video);
                return;
            }

            requestFrame(() => {
                if (!slide.classList.contains('is-active') || !isSlidePlayable(slide)) return;

                const delay = templateVideoStartDelay(video);
                if (delay <= 0) {
                    playVideoFromStart(video);
                    return;
                }

                const startTimer = window.setTimeout(() => {
                    if (slide.classList.contains('is-active') && isSlidePlayable(slide)) {
                        playVideoFromStart(video);
                    } else {
                        videoStartTimers.delete(video);
                    }
                }, delay);
                videoStartTimers.set(video, startTimer);
            });
        });
    };

    const waitForMediaElement = element => new Promise(resolve => {
        const tag = element.tagName;
        const isReady = () => tag === 'IMG'
            ? element.complete && Number(element.naturalWidth || 0) > 0
            : (tag === 'VIDEO' ? element.readyState >= 2 : false);
        if (isReady()) {
            if (tag === 'IMG' && typeof element.decode === 'function') {
                element.decode().then(() => resolve(true), () => resolve(false));
                return;
            }
            resolve(true);
            return;
        }
        let settled = false;
        const finish = ready => {
            if (settled) return;
            settled = true;
            window.clearTimeout(timeout);
            element.removeEventListener('load', loaded);
            element.removeEventListener('canplay', loaded);
            element.removeEventListener('error', failed);
            resolve(ready);
        };
        const loaded = () => {
            if (tag === 'IMG' && typeof element.decode === 'function') {
                element.decode().then(() => finish(true), () => finish(false));
                return;
            }
            finish(true);
        };
        const failed = () => finish(false);
        const timeout = window.setTimeout(() => finish(false), MEDIA_READY_TIMEOUT_MS);
        element.addEventListener('load', loaded);
        element.addEventListener('canplay', loaded);
        element.addEventListener('error', failed);
        if (isReady()) loaded();
    });

    const waitForBackgroundImage = element => new Promise(resolve => {
        const source = element.dataset.bgSrc || '';
        if (!source) { resolve(true); return; }
        const probe = new Image();
        let settled = false;
        const finish = ready => {
            if (settled) return;
            settled = true;
            window.clearTimeout(timeout);
            if (ready) {
                element.classList.remove('is-media-error');
                delete element.dataset.mediaFailedAt;
                element.style.backgroundImage = 'url(' + encodeURI(source) + ')';
            } else {
                element.classList.add('is-media-error');
                element.dataset.mediaFailedAt = String(Date.now());
            }
            resolve(ready);
        };
        const timeout = window.setTimeout(() => finish(false), MEDIA_READY_TIMEOUT_MS);
        probe.onload = () => finish(true);
        probe.onerror = () => finish(false);
        probe.src = source;
        if (probe.complete) finish(Number(probe.naturalWidth || 0) > 0);
    });

    const waitForSlideMedia = slide => {
        if (!slide) return Promise.resolve(false);
        ensureMediaLoaded(slide);
        const required = [
            ...Array.from(slide.querySelectorAll('img[data-src], video[data-src]')).map(waitForMediaElement),
            ...Array.from(slide.querySelectorAll('.text-slide-background--image[data-bg-src]')).map(waitForBackgroundImage),
        ];
        return required.length === 0 ? Promise.resolve(true) : Promise.all(required).then(results => results.every(Boolean));
    };

    const reloadIfChanged = (source = 'state-check') => {
        const url = resolveEndpointUrl(slideshow.dataset.stateUrl);
        if (!url || !window.fetch || stateRequestInFlight) {
            logSyncDebug('state check skipped', {
                source,
                hasUrl: Boolean(url),
                hasFetch: Boolean(window.fetch),
                stateRequestInFlight,
            });
            return Promise.resolve();
        }

        stateRequestInFlight = true;
        logSyncDebug('state check request', {
            source,
            url,
        });

        return fetchWithTimeout(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        }, STATE_REQUEST_TIMEOUT_MS)
            .then(response => {
                logSyncDebug('state check response', {
                    source,
                    status: response.status,
                    ok: response.ok,
                });
                if (response.status === 404 || response.status === 409) {
                    const stateData = { signature: `state-http-${response.status}` };
                    if (shouldUseSyncedGroupReload(stateData)) {
                        logSyncDebug('state check schedules synced reload for HTTP status', {
                            source,
                            status: response.status,
                        });
                        scheduleSyncedReload(`state-http-${response.status}`, stateData);
                    } else {
                        logSyncDebug('state check reloads immediately for HTTP status', {
                            source,
                            status: response.status,
                        });
                        reloadImmediately(`state-http-${response.status}`, stateData);
                    }
                    return null;
                }

                return response.ok ? response.json() : null;
            })
            .then(data => {
                if (!data) {
                    throw new Error('State endpoint returned no usable payload.');
                }
                stateFailureCount = 0;
                if (stateRetryTimer) {
                    window.clearTimeout(stateRetryTimer);
                    stateRetryTimer = null;
                }
                const previousOffsetMs = serverClockOffsetMs;
                const updatedClock = updateServerClock(data.server_time_ms);
                queueSelectionBoundaryCheck(data.next_selection_at_ms);
                logSyncDebug('state check payload', {
                    source,
                    ok: data.ok ?? null,
                    signature: data.signature || '',
                    currentSignature,
                    serverTimeMs: data.server_time_ms || null,
                    updatedClock,
                    serverClockOffsetDeltaMs: Math.round(serverClockOffsetMs - previousOffsetMs),
                    stateGroup: displayGroupFromState(data),
                });
                if (data.ok === false) {
                    const stateData = Object.assign({ signature: 'state-error' }, data);
                    if (shouldUseSyncedGroupReload(stateData)) {
                        logSyncDebug('state check schedules synced reload for state error', { source });
                        scheduleSyncedReload('state-error', stateData);
                    } else {
                        logSyncDebug('state check reloads immediately for state error', { source });
                        reloadImmediately('state-error', stateData);
                    }
                    return;
                }
                if (!data.signature) {
                    logSyncDebug('state check missing signature', { source });
                    return;
                }
                if (!currentSignature) {
                    currentSignature = data.signature;
                    logSyncDebug('state check initialized current signature', {
                        source,
                        currentSignature,
                    });
                    warmOfflineCache('state-check');
                    return;
                }
                if (data.signature === currentSignature) {
                    stateFailureCount = 0;
                    logSyncDebug('state check no change', {
                        source,
                        signature: data.signature,
                    });
                    warmOfflineCache('state-check');
                    return;
                }
                if (data.signature !== currentSignature) {
                    const useSyncedReload = shouldUseSyncedGroupReload(data);
                    logReload('Config change detected', {
                        currentSignature,
                        nextSignature: data.signature,
                        displayGroup: displayGroupFromState(data),
                        synchronizedGroup: useSyncedReload,
                    });
                    logSyncDebug('state check detected signature change', {
                        source,
                        currentSignature,
                        nextSignature: data.signature,
                        synchronizedGroup: useSyncedReload,
                        displayGroup: displayGroupFromState(data),
                    });

                    if (useSyncedReload) {
                        scheduleSyncedReload('signature-changed', data);
                        return;
                    }

                    reloadImmediately('signature-changed', data);
                }
            })
            .catch(error => {
                stateFailureCount += 1;
                logSyncDebug('state check failed', {
                    source,
                    error: String(error?.message || error),
                    consecutiveFailures: stateFailureCount,
                });
            })
            .finally(() => {
                stateRequestInFlight = false;
                logSyncDebug('state check complete', { source });
                if (stateFailureCount > 0 && !stateRetryTimer) {
                    const retryDelay = Math.min(30000, 2000 * (2 ** Math.min(stateFailureCount - 1, 4)));
                    stateRetryTimer = window.setTimeout(() => {
                        stateRetryTimer = null;
                        reloadIfChanged('state-retry');
                    }, retryDelay);
                }
            });
    };

    const queueNext = () => {
        clearTimeout(timer);

        if (!startupComplete || slides.length <= 1) {
            return;
        }

        const targetIndex = nextPlayableIndex(index);
        if (targetIndex < 0 || targetIndex === index) {
            nextSlideDueAt = 0;
            return;
        }

        const delay = durationForSlide(slides[index]);
        nextSlideDueAt = Date.now() + delay;
        timer = window.setTimeout(() => {
            activate(targetIndex);
        }, delay);
    };

    const activate = nextSlideIndex => {
        if (!startupComplete || transitionInFlight) return;

        if (!isSlidePlayable(slides[nextSlideIndex])) {
            nextSlideIndex = nextPlayableIndex(index);
        }

        const current = slides[index];
        const next = slides[nextSlideIndex];
        if (!next || next === current) {
            queueNext();
            return;
        }

        transitionInFlight = true;
        waitForSlideMedia(next).then(ready => {
            if (!ready || !isSlidePlayable(next)) {
                logReload('Skipping slide because required media is not renderable', {
                    slideId: next.dataset.slideId || '',
                    slideType: next.dataset.slideType || '',
                });
                return;
            }

            return new Promise(resolve => requestFrame(() => {
                try {
                    stopVideo(current);
                    current.classList.remove('is-active');
                    current.classList.remove('is-text-card-animating');
                    current.classList.remove('is-template-animating');
                    next.classList.add('is-active');
                    index = nextSlideIndex;
                    restartTextCardAnimation(next);
                    restartTemplateElementAnimations(next);
                    startVideo(next);
                    prepareMediaAround(index);
                    window.setTimeout(() => cleanupFarMedia(index), 1300);
                } catch (error) {
                    window.console?.error?.('[Hugin display] Slide transition failed', error);
                } finally {
                    transitionInFlight = false;
                    queueNext();
                    resolve();
                }
            }));
        }).catch(error => {
            window.console?.error?.('[Hugin display] Media readiness check failed', error);
        }).finally(() => {
            if (transitionInFlight) {
                transitionInFlight = false;
                queueNext();
            }
        });
    };

    const queueStateCheck = () => {
        clearInterval(stateTimer);
        if (shouldUseSyncedGroupReload()) {
            stateTimer = null;
            logSyncDebug('standard interval state check disabled for synced group');
            return;
        }
        logSyncDebug('standard interval state check queued', {
            intervalMs: stateCheckIntervalMs(),
        });
        stateTimer = setInterval(reloadIfChanged, stateCheckIntervalMs());
    };

    const queueMinuteAlignedStateCheck = () => {
        clearTimeout(scheduleStateTimer);
        const waitMs = msUntilNextMinuteTick();
        logSyncDebug('minute-aligned state check queued', {
            waitMs,
            nextCheckServerAt: new Date(serverNowMs() + waitMs).toISOString(),
        });
        scheduleStateTimer = window.setTimeout(() => {
            logSyncDebug('minute-aligned state check firing');
            Promise.resolve(reloadIfChanged('minute-aligned')).then(
                queueMinuteAlignedStateCheck,
                queueMinuteAlignedStateCheck
            );
        }, waitMs);
    };

    const queueWatchdog = () => {
        clearInterval(watchdogTimer);
        watchdogTimer = setInterval(() => {
            if (!startupComplete || slides.length <= 1 || !nextSlideDueAt) return;

            const lateBy = Date.now() - nextSlideDueAt;
            if (lateBy > Math.max(5000, durationForSlide(slides[index]))) {
                const targetIndex = nextPlayableIndex(index);
                if (targetIndex >= 0 && targetIndex !== index) {
                    activate(targetIndex);
                }
            }
        }, 5000);
    };

    const startSlideshow = () => {
        startupComplete = true;
        markStartupSeen();
        slideshow.classList.remove('is-startup-sync-pending');
        queueSelectionBoundaryCheck(nextSelectionAtMs);
        if (slides.length === 0) {
            setStartupStage('starting');
            reloadIfChanged('startup');
            queueStateCheck();
            queueMinuteAlignedStateCheck();
            return;
        }
        if (!isSlidePlayable(slides[index])) {
            const playable = firstPlayableIndex();
            if (playable >= 0 && playable !== index) {
                slides[index].classList.remove('is-active');
                slides[index].classList.remove('is-template-animating');
                slides[playable].classList.add('is-active');
                index = playable;
            }
        }
        prepareMediaAround(index);
        restartTextCardAnimation(slides[index]);
        restartTemplateElementAnimations(slides[index]);
        startVideo(slides[index]);
        cleanupFarMedia(index);
        setStartupStage('starting');
        logSyncDebug('slideshow started', {
            activeIndex: index,
            activeSlideId: slides[index]?.dataset.slideId || '',
        });
        reloadIfChanged('startup');
        queueStateCheck();
        queueMinuteAlignedStateCheck();
        queueWatchdog();
        queueNext();
    };

    const prepareStartup = () => {
        const scheduledReload = shouldUseSyncedGroupReload() ? readScheduledSyncReload() : null;
        const fallbackStartAtMs = Number(scheduledReload?.startAtMs || 0);

        return warmOfflineCache('startup')
            .then(cacheResult => {
                if (!shouldUseSyncedGroupReload()) {
                    return postCacheReadiness('startup', cacheResult)
                        .catch(() => null)
                        .then(waitForStartupSync);
                }

                return waitForCacheReadinessRelease('startup', cacheResult)
                    .then(status => waitForReadinessStart(status, { fallbackStartAtMs }));
            })
            .then(() => {
                setStartupStage('starting');
                return waitForSlideImages(slides[index]);
            });
    };

    const prepareStartupWithDeadline = () => {
        const preparation = prepareStartup();
        const deadline = sleep(STARTUP_MAX_WAIT_MS).then(() => {
            logSyncDebug('startup deadline reached; playback is being released fail-open', {
                maxWaitMs: STARTUP_MAX_WAIT_MS,
            });

            if (!shouldUseSyncedGroupReload()) {
                return null;
            }

            // If coordination itself is unavailable, use the same predictable
            // minute edge on every group member instead of leaving the loader up.
            const fallbackStartAtMs = computeNextFullMinuteActivation();
            return waitForStartupMinute(fallbackStartAtMs);
        });

        return Promise.race([preparation, deadline]);
    };

    window.addEventListener('online', () => {
        warmOfflineCache('online');
        if (shouldUseSyncedGroupReload()) {
            logSyncDebug('online event: synced group keeps minute-aligned state check');
            queueMinuteAlignedStateCheck();
            return;
        }
        logSyncDebug('online event: immediate state check for non-synced display');
        reloadIfChanged('online');
    });
    window.addEventListener('offline', () => {
        if (!isSlidePlayable(slides[index])) {
            const playable = firstPlayableIndex();
            if (playable >= 0 && playable !== index) {
                activate(playable);
            }
        }
    });
    window.addEventListener('resize', () => {
        clearTimeout(window.__huginQrResize);
        window.__huginQrResize = setTimeout(renderTextSlideQrCodes, 250);
    });
    window.addEventListener('focus', () => {
        updateTemplateTimedElements();
        if (nextSlideDueAt && Date.now() >= nextSlideDueAt) {
            activate(nextIndex(index));
        }
        if (shouldUseSyncedGroupReload()) {
            logSyncDebug('focus event: synced group keeps minute-aligned state check');
            queueMinuteAlignedStateCheck();
            return;
        }
        logSyncDebug('focus event: immediate state check for non-synced display');
        reloadIfChanged('focus');
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateTemplateTimedElements();
        }
        if (document.visibilityState === 'visible' && nextSlideDueAt && Date.now() >= nextSlideDueAt) {
            activate(nextIndex(index));
        }
    });
    renderTextSlideQrCodes();
    initializeTemplateDynamicTextElements();
    if (updateTemplateTimedElements()) {
        window.setInterval(updateTemplateTimedElements, 1000);
    }
    // Prime the current and next slide while the startup overlay is visible.
    // Field-selected template images are often not in the browser cache yet.
    prepareMediaAround(index);
    prepareStartupWithDeadline().then(startSlideshow, startSlideshow);
})();
