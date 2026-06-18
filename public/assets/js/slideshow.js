(() => {
    const slideshow = document.getElementById('slideshow');
    if (!slideshow) return;

    const slides = Array.from(document.querySelectorAll('.slide'));
    if (slides.length === 0) {
        slideshow.classList.remove('is-startup-sync-pending');
        return;
    }

    let index = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
    let timer = null;
    let heartbeatTimer = null;
    let stateTimer = null;
    let scheduleStateTimer = null;
    let watchdogTimer = null;
    let pendingReloadTimer = null;
    let pendingReload = null;
    let nextSlideDueAt = 0;
    let startupComplete = false;
    let stateRequestInFlight = false;
    let currentSignature = slideshow.dataset.stateSignature || '';
    const videoStartTimers = new WeakMap();
    const MINUTE_MS = 60000;
    const SYNC_RELOAD_MIN_LEAD_MS = 3000;
    const SCHEDULED_SYNC_RELOAD_KEY = 'huginScheduledSyncReload';
    const SCHEDULED_SYNC_RELOAD_MAX_AGE_MS = 120000;
    let serverClockOffsetMs = 0;
    const requestFrame = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : (callback => window.setTimeout(callback, 16));

    const updateServerClock = value => {
        const serverTimeMs = Number(value);
        if (!Number.isFinite(serverTimeMs) || serverTimeMs <= 0) {
            return false;
        }

        serverClockOffsetMs = serverTimeMs - Date.now();
        return true;
    };

    updateServerClock(slideshow.dataset.serverTimeMs);

    const serverNowMs = () => Date.now() + serverClockOffsetMs;

    const delayUntilServerTime = targetMs => Math.max(0, Math.ceil(Number(targetMs || 0) - serverNowMs()));

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
        });
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

        return navigator.serviceWorker.register(serviceWorkerUrl, { scope: '/' })
            .then(() => navigator.serviceWorker.ready)
            .then(() => true)
            .catch(() => false);
    };

    const serviceWorkerReady = registerDisplayServiceWorker();

    const postServiceWorkerMessage = (type, payload = {}) => serviceWorkerReady.then(ready => {
        if (!ready || !navigator.serviceWorker) {
            throw new Error('Display service worker is unavailable.');
        }

        return navigator.serviceWorker.ready.then(registration => new Promise((resolve, reject) => {
            const worker = registration.active || navigator.serviceWorker.controller;
            if (!worker) {
                reject(new Error('Display service worker is not active.'));
                return;
            }

            const channel = new MessageChannel();
            const timeout = window.setTimeout(() => reject(new Error('Display service worker timed out.')), 45000);
            channel.port1.onmessage = event => {
                window.clearTimeout(timeout);
                const data = event.data || {};
                if (data.ok === false) {
                    reject(new Error(data.error || 'Display service worker request failed.'));
                    return;
                }
                resolve(data);
            };
            worker.postMessage(Object.assign({ type }, payload), [channel.port2]);
        }));
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

        return fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        })
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

    const warmOfflineCache = (reason = 'startup') => {
        if (reason === 'state-check' && currentSignature && lastWarmSignature === currentSignature) {
            return Promise.resolve({ manifest: null, offlinePlayableCount: 0, cachedUrls: Array.from(cachedAssetUrls), reason });
        }
        if (offlineCacheWarmPromise) return offlineCacheWarmPromise;

        offlineCacheWarmPromise = fetchOfflineManifest()
            .then(manifest => {
                if (!manifest) return { manifest: null, offlinePlayableCount: 0, cachedUrls: Array.from(cachedAssetUrls) };
                applyManifestSlidePolicies(manifest);
                return resolveOfflineCacheBudget()
                    .then(maxBytes => postServiceWorkerMessage('CACHE_DISPLAY_MANIFEST', { manifest, maxBytes }))
                    .then(result => {
                        mergeCachedUrls(result.cachedUrls || []);
                        lastWarmSignature = manifest.signature || lastWarmSignature;
                        return {
                            manifest,
                            offlinePlayableCount: offlinePlayableCount(manifest, result.cachedUrls || []),
                            cachedUrls: result.cachedUrls || [],
                            reason,
                        };
                    })
                    .catch(() => ({
                        manifest,
                        offlinePlayableCount: offlinePlayableCount(manifest, Array.from(cachedAssetUrls)),
                        cachedUrls: Array.from(cachedAssetUrls),
                        reason,
                    }));
            })
            .then(result => {
                offlineCacheWarmPromise = null;
                return result;
            })
            .catch(error => {
                offlineCacheWarmPromise = null;
                throw error;
            });

        return offlineCacheWarmPromise;
    };

    const prepareOfflineCacheForReload = stateData => warmOfflineCache('config-reload')
        .then(result => ({ defer: Boolean(result.manifest && result.offlinePlayableCount <= 0), result }))
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


    const heartbeatIntervalMs = () => {
        const seconds = parseInt(slideshow.dataset.heartbeatInterval || '90', 10);
        return Math.max(seconds || 90, 30) * 1000;
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
        const startAtMs = reload?.activateAtMs ? computeNextFullMinuteActivation(Number(reload.activateAtMs) + 1) : 0;
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

    const applyPendingReload = () => {
        if (!pendingReload) return;

        const reload = pendingReload;
        pendingReload = null;
        pendingReloadTimer = null;

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
        });

        markScheduledSyncReload(reload);
        window.location.reload();
    };

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
        const activateAtMs = computeNextFullMinuteActivation();
        const replaced = Boolean(pendingReloadTimer);
        if (pendingReloadTimer) {
            clearTimeout(pendingReloadTimer);
        }

        const scheduleReload = () => {
            pendingReload = {
                reason,
                stateData,
                signature,
                displayGroup,
                activateAtMs,
            };
            pendingReloadTimer = window.setTimeout(applyPendingReload, delayUntilServerTime(activateAtMs));

            logReload(replaced ? 'Replaced pending synchronized reload' : 'Scheduled synchronized reload', {
                reason,
                displayGroup,
                signature,
                activateAt: new Date(activateAtMs).toISOString(),
            });
            logSyncDebug(replaced ? 'replaced pending synchronized reload' : 'scheduled synchronized reload', {
                reason,
                signature,
                displayGroup,
                stateServerTimeMs: stateData?.server_time_ms || null,
                activateAt: new Date(activateAtMs).toISOString(),
                msUntilActivate: delayUntilServerTime(activateAtMs),
            });
        };

        runWhenOfflineReady(stateData, scheduleReload);
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
        return fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        })
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
                return new Promise(resolve => {
                    window.setTimeout(resolve, delayUntilServerTime(targetMs));
                });
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

    const unloadHeavyMedia = slide => {
        if (!slide) return;

        slide.querySelectorAll('video[data-src]').forEach(video => {
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

    const stopVideo = slide => {
        slide?.querySelectorAll('video').forEach(video => {
            const startTimer = videoStartTimers.get(video);
            if (startTimer) {
                window.clearTimeout(startTimer);
                videoStartTimers.delete(video);
            }
            video.pause();
            video.currentTime = 0;
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
        videoStartTimers.delete(video);
        video.currentTime = 0;
        video.play().catch(() => {});
    };

    const startVideo = slide => {
        if (!slide || !isSlidePlayable(slide)) return;

        ensureMediaLoaded(slide);
        slide.querySelectorAll('video').forEach(video => {
            const existingStartTimer = videoStartTimers.get(video);
            if (existingStartTimer) {
                window.clearTimeout(existingStartTimer);
                videoStartTimers.delete(video);
            }

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

    const ua = navigator.userAgent || '';
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
            { name: 'iOS', regex: /OS ([\d_]+) like Mac OS X/i, transform: v => v.replace(/_/g, '.') },
            { name: 'macOS', regex: /Mac OS X ([\d_]+)/i, transform: v => v.replace(/_/g, '.') },
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

    const collectHeartbeatPayload = () => {
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

    const sendHeartbeatBeacon = (url, payload) => {
        if (!navigator.sendBeacon) return false;
        const blob = new Blob([payload], { type: 'application/json' });
        return navigator.sendBeacon(url, blob);
    };

    const sendHeartbeat = (options = {}) => {
        const url = resolveEndpointUrl(slideshow.dataset.heartbeatUrl);
        if (!url) return;

        const payload = JSON.stringify(collectHeartbeatPayload());

        if (options.preferBeacon && sendHeartbeatBeacon(url, payload)) {
            return;
        }

        if (!window.fetch) {
            sendHeartbeatBeacon(url, payload);
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: payload,
            cache: 'no-store',
            credentials: 'same-origin',
            keepalive: true,
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Heartbeat failed with HTTP ${response.status}`);
                }
            })
            .catch(() => {
                sendHeartbeatBeacon(url, payload);
            });
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

        return fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        })
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
                    logSyncDebug('state check had no JSON payload', { source });
                    return;
                }
                const previousOffsetMs = serverClockOffsetMs;
                const updatedClock = updateServerClock(data.server_time_ms);
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
                logSyncDebug('state check failed', {
                    source,
                    error: String(error?.message || error),
                });
            })
            .then(() => {
                stateRequestInFlight = false;
                logSyncDebug('state check complete', { source });
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
        if (!startupComplete) return;

        if (!isSlidePlayable(slides[nextSlideIndex])) {
            nextSlideIndex = nextPlayableIndex(index);
        }

        const current = slides[index];
        const next = slides[nextSlideIndex];
        if (!next || next === current) {
            queueNext();
            return;
        }

        ensureMediaLoaded(next);

        requestFrame(() => {
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
            queueNext();
        });
    };

    const queueHeartbeat = () => {
        clearInterval(heartbeatTimer);
        sendHeartbeat();
        heartbeatTimer = setInterval(sendHeartbeat, heartbeatIntervalMs());
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
        warmOfflineCache('startup');
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

    window.addEventListener('online', () => {
        sendHeartbeat();
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
    window.addEventListener('pagehide', () => sendHeartbeat({ preferBeacon: true }));
    window.addEventListener('resize', () => {
        clearTimeout(window.__huginResizeHeartbeat);
        window.__huginResizeHeartbeat = setTimeout(sendHeartbeat, 600);
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
    if (screen.orientation?.addEventListener) {
        screen.orientation.addEventListener('change', sendHeartbeat);
    }

    renderTextSlideQrCodes();
    if (updateTemplateTimedElements()) {
        window.setInterval(updateTemplateTimedElements, 1000);
    }
    prepareMediaAround(index);
    queueHeartbeat();
    waitForStartupSync().then(startSlideshow);
})();
