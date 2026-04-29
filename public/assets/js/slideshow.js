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
    let nextSlideDueAt = 0;
    let startupComplete = false;
    let stateRequestInFlight = false;
    let currentSignature = slideshow.dataset.stateSignature || '';
    const requestFrame = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : (callback => window.setTimeout(callback, 16));

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

    const msUntilNextMinuteTick = () => {
        const now = new Date();
        const msIntoMinute = (now.getSeconds() * 1000) + now.getMilliseconds();
        const targetMs = 50;

        if (msIntoMinute < targetMs) {
            return targetMs - msIntoMinute;
        }

        return 60000 - msIntoMinute + targetMs;
    };

    const shouldWaitForStartupSync = () => {
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
        if (!slide) return;

        slide.querySelectorAll('img[data-src], video[data-src], iframe[data-src]').forEach(element => {
            const source = element.dataset.src;
            if (!source || element.getAttribute('src')) return;

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
    };

    const prepareMediaAround = activeIndex => {
        ensureMediaLoaded(slides[activeIndex]);
        if (slides.length > 1) {
            ensureMediaLoaded(slides[nextIndex(activeIndex)]);
        }
    };

    const cleanupFarMedia = activeIndex => {
        const keep = new Set([activeIndex]);
        if (slides.length > 1) {
            keep.add(nextIndex(activeIndex));
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
        if (video) {
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
                    window.location.reload();
                    return null;
                }

                return response.ok ? response.json() : null;
            })
            .then(data => {
                if (!data) return;
                if (data.ok === false) {
                    window.location.reload();
                    return;
                }
                if (!data.signature) return;
                if (!currentSignature) {
                    currentSignature = data.signature;
                    return;
                }
                if (data.signature !== currentSignature) {
                    window.location.reload();
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

        const delay = durationForSlide(slides[index]);
        nextSlideDueAt = Date.now() + delay;
        timer = window.setTimeout(() => {
            activate(nextIndex(index));
        }, delay);
    };

    const activate = nextSlideIndex => {
        if (!startupComplete) return;

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
            next.classList.add('is-active');
            index = nextSlideIndex;
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
                activate(nextIndex(index));
            }
        }, 5000);
    };

    const startSlideshow = () => {
        startupComplete = true;
        slideshow.classList.remove('is-startup-sync-pending');
        prepareMediaAround(index);
        startVideo(slides[index]);
        cleanupFarMedia(index);
        reloadIfChanged();
        queueStateCheck();
        queueMinuteAlignedStateCheck();
        queueWatchdog();
        queueNext();
    };

    window.addEventListener('online', () => {
        sendHeartbeat();
        reloadIfChanged();
    });
    window.addEventListener('pagehide', () => sendHeartbeat({ preferBeacon: true }));
    window.addEventListener('resize', () => {
        clearTimeout(window.__huginResizeHeartbeat);
        window.__huginResizeHeartbeat = setTimeout(sendHeartbeat, 600);
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

    prepareMediaAround(index);
    queueHeartbeat();
    waitForStartupSync().then(startSlideshow);
})();
