(() => {
    const slideshow = document.getElementById('slideshow');
    if (!slideshow) return;

    const slides = Array.from(document.querySelectorAll('.slide'));
    if (slides.length === 0) return;

    let index = 0;
    let timer = null;
    let heartbeatTimer = null;
    let stateTimer = null;
    let currentSignature = slideshow.dataset.stateSignature || '';

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

    const sendHeartbeat = () => {
        const url = slideshow.dataset.heartbeatUrl;
        if (!url) return;

        const payload = JSON.stringify(collectHeartbeatPayload());

        if (navigator.sendBeacon) {
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload,
            keepalive: true,
        }).catch(() => {});
    };

    const reloadIfChanged = () => {
        const url = slideshow.dataset.stateUrl;
        if (!url) return;

        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        })
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                if (!data || !data.signature) return;
                if (!currentSignature) {
                    currentSignature = data.signature;
                    return;
                }
                if (data.signature !== currentSignature) {
                    window.location.reload();
                }
            })
            .catch(() => {});
    };

    const activate = nextIndex => {
        const current = slides[index];
        const next = slides[nextIndex];
        if (!next || next === current) {
            queueNext();
            return;
        }

        stopVideo(current);
        current.classList.remove('is-active');
        next.classList.add('is-active');
        startVideo(next);
        index = nextIndex;
        queueNext();
    };

    const queueNext = () => {
        clearTimeout(timer);
        const active = slides[index];
        const seconds = parseInt(active?.dataset.duration || slideshow.dataset.defaultDuration || '8', 10);
        timer = setTimeout(() => activate((index + 1) % slides.length), Math.max(seconds, 1) * 1000);
    };

    const queueHeartbeat = () => {
        clearInterval(heartbeatTimer);
        sendHeartbeat();
        heartbeatTimer = setInterval(sendHeartbeat, 5 * 60 * 1000);
    };

    const queueStateCheck = () => {
        clearInterval(stateTimer);
        stateTimer = setInterval(reloadIfChanged, 60 * 1000);
    };

    window.addEventListener('online', sendHeartbeat);
    window.addEventListener('resize', () => {
        clearTimeout(window.__huginResizeHeartbeat);
        window.__huginResizeHeartbeat = setTimeout(sendHeartbeat, 600);
    });
    if (screen.orientation?.addEventListener) {
        screen.orientation.addEventListener('change', sendHeartbeat);
    }

    startVideo(slides[0]);
    queueHeartbeat();
    queueStateCheck();
    if (slides.length > 1) {
        queueNext();
    }
})();
