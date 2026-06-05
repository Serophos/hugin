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
    const MINUTE_MS = 60000;
    const SYNC_RELOAD_MIN_LEAD_MS = 3000;
    const SCHEDULED_SYNC_RELOAD_KEY = 'huginScheduledSyncReload';
    const SCHEDULED_SYNC_RELOAD_MAX_AGE_MS = 120000;
    const requestFrame = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : (callback => window.setTimeout(callback, 16));

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

    const shouldUseSyncedGroupReload = stateData => displayGroupFromState(stateData).sync_reload_to_full_minute === true;

    const computeNextFullMinuteActivation = (nowMs = Date.now()) => {
        const msIntoMinute = nowMs % MINUTE_MS;
        const nextMinute = msIntoMinute === 0 ? nowMs + MINUTE_MS : nowMs + (MINUTE_MS - msIntoMinute);

        if (nextMinute - nowMs < SYNC_RELOAD_MIN_LEAD_MS) {
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
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
                return data;
            }

            if (raw) {
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
            }
        } catch (error) {
            try {
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
            } catch (storageError) {}
        }

        document.documentElement.classList.remove('hugin-scheduled-sync-reload');
        return null;
    };

    const markScheduledSyncReload = () => {
        try {
            window.sessionStorage.setItem(SCHEDULED_SYNC_RELOAD_KEY, JSON.stringify({
                at: Date.now(),
                reason: 'sync-group-config-reload',
            }));
        } catch (error) {}
    };

    const reloadImmediately = (reason, stateData = null) => {
        if (pendingReloadTimer) {
            clearTimeout(pendingReloadTimer);
        }
        pendingReloadTimer = null;
        pendingReload = null;

        runWhenOfflineReady(stateData, () => {
            logReload('Applying immediate reload', {
                reason,
                displayGroup: displayGroupFromState(stateData),
                signature: stateData?.signature || '',
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

        markScheduledSyncReload();
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
            return;
        }

        const activateAtMs = computeNextFullMinuteActivation(Date.now());
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
            pendingReloadTimer = window.setTimeout(applyPendingReload, Math.max(0, activateAtMs - Date.now()));

            logReload(replaced ? 'Replaced pending synchronized reload' : 'Scheduled synchronized reload', {
                reason,
                displayGroup,
                signature,
                activateAt: new Date(activateAtMs).toISOString(),
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
        const now = new Date();
        const msIntoMinute = (now.getSeconds() * 1000) + now.getMilliseconds();
        const targetMs = 50;

        if (msIntoMinute < targetMs) {
            return targetMs - msIntoMinute;
        }

        return MINUTE_MS - msIntoMinute + targetMs;
    };

    const shouldWaitForStartupSync = () => {
        const scheduledReload = readScheduledSyncReload();
        if (scheduledReload) {
            logReload('Skipping startup sync after scheduled synchronized reload', scheduledReload);
            return false;
        }

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

    const waitForStartupSync = () => {
        if (!shouldWaitForStartupSync()) {
            return Promise.resolve();
        }

        return new Promise(resolve => {
            window.setTimeout(resolve, msUntilNextMinuteTick());
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
        const video = slide?.querySelector('video');
        if (video) {
            video.pause();
            video.currentTime = 0;
        }
    };

    const startVideo = slide => {
        const video = slide?.querySelector('video');
        if (video && isSlidePlayable(slide)) {
            ensureMediaLoaded(slide);
            video.currentTime = 0;
            video.play().catch(() => {});
        }
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

    const reloadIfChanged = () => {
        const url = resolveEndpointUrl(slideshow.dataset.stateUrl);
        if (!url || !window.fetch || stateRequestInFlight) return Promise.resolve();

        stateRequestInFlight = true;

        return fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then(response => {
                if (response.status === 404 || response.status === 409) {
                    const stateData = { signature: `state-http-${response.status}` };
                    if (shouldUseSyncedGroupReload(stateData)) {
                        scheduleSyncedReload(`state-http-${response.status}`, stateData);
                    } else {
                        reloadImmediately(`state-http-${response.status}`, stateData);
                    }
                    return null;
                }

                return response.ok ? response.json() : null;
            })
            .then(data => {
                if (!data) return;
                if (data.ok === false) {
                    const stateData = Object.assign({ signature: 'state-error' }, data);
                    if (shouldUseSyncedGroupReload(stateData)) {
                        scheduleSyncedReload('state-error', stateData);
                    } else {
                        reloadImmediately('state-error', stateData);
                    }
                    return;
                }
                if (!data.signature) return;
                if (!currentSignature) {
                    currentSignature = data.signature;
                    warmOfflineCache('state-check');
                    return;
                }
                if (data.signature === currentSignature) {
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

                    if (useSyncedReload) {
                        scheduleSyncedReload('signature-changed', data);
                        return;
                    }

                    reloadImmediately('signature-changed', data);
                }
            })
            .catch(() => {})
            .then(() => {
                stateRequestInFlight = false;
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
            next.classList.add('is-active');
            index = nextSlideIndex;
            restartTextCardAnimation(next);
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
        stateTimer = setInterval(reloadIfChanged, stateCheckIntervalMs());
    };

    const queueMinuteAlignedStateCheck = () => {
        clearTimeout(scheduleStateTimer);
        scheduleStateTimer = window.setTimeout(() => {
            reloadIfChanged();
            queueMinuteAlignedStateCheck();
        }, msUntilNextMinuteTick());
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
        slideshow.classList.remove('is-startup-sync-pending');
        document.documentElement.classList.remove('hugin-scheduled-sync-reload');
        if (!isSlidePlayable(slides[index])) {
            const playable = firstPlayableIndex();
            if (playable >= 0 && playable !== index) {
                slides[index].classList.remove('is-active');
                slides[playable].classList.add('is-active');
                index = playable;
            }
        }
        prepareMediaAround(index);
        startVideo(slides[index]);
        restartTextCardAnimation(slides[index]);
        cleanupFarMedia(index);
        warmOfflineCache('startup');
        reloadIfChanged();
        queueStateCheck();
        queueMinuteAlignedStateCheck();
        queueWatchdog();
        queueNext();
    };

    window.addEventListener('online', () => {
        sendHeartbeat();
        warmOfflineCache('online');
        reloadIfChanged();
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
        if (nextSlideDueAt && Date.now() >= nextSlideDueAt) {
            activate(nextIndex(index));
        }
        reloadIfChanged();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && nextSlideDueAt && Date.now() >= nextSlideDueAt) {
            activate(nextIndex(index));
        }
    });
    if (screen.orientation?.addEventListener) {
        screen.orientation.addEventListener('change', sendHeartbeat);
    }

    renderTextSlideQrCodes();
    prepareMediaAround(index);
    queueHeartbeat();
    waitForStartupSync().then(startSlideshow);
})();
