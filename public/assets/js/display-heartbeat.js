(() => {
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
    const ua = navigator.userAgent || '';
    let timer = null;
    let watchdog = null;
    let requestInFlight = false;
    let lastAttemptAt = 0;
    let consecutiveFailures = 0;

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
    const sendBeacon = payload => {
        if (!navigator.sendBeacon) return false;
        return navigator.sendBeacon(heartbeatUrl, new Blob([payload], { type: 'application/json' }));
    };

    const retryDelayMs = () => consecutiveFailures <= 0
        ? intervalMs
        : Math.min(intervalMs, 5000 * (2 ** Math.min(consecutiveFailures - 1, 4)));

    const scheduleNext = delayMs => {
        window.clearTimeout(timer);
        timer = window.setTimeout(send, Math.max(1000, Number(delayMs || intervalMs)));
    };

    const send = ({ preferBeacon = false } = {}) => {
        const payload = payloadJson();
        lastAttemptAt = Date.now();

        if (preferBeacon && sendBeacon(payload)) return Promise.resolve(true);
        if (requestInFlight) return Promise.resolve(false);
        if (!window.fetch) {
            const queued = sendBeacon(payload);
            consecutiveFailures = queued ? 0 : consecutiveFailures + 1;
            scheduleNext(retryDelayMs());
            return Promise.resolve(queued);
        }

        requestInFlight = true;
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timeout = window.setTimeout(() => controller?.abort(), requestTimeoutMs);

        return fetch(heartbeatUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: payload,
            cache: 'no-store',
            credentials: 'same-origin',
            keepalive: true,
            ...(controller ? { signal: controller.signal } : {}),
        })
            .then(response => {
                if (!response.ok) throw new Error(`Heartbeat failed with HTTP ${response.status}`);
                consecutiveFailures = 0;
                return true;
            })
            .catch(() => {
                consecutiveFailures += 1;
                sendBeacon(payload);
                return false;
            })
            .finally(() => {
                window.clearTimeout(timeout);
                requestInFlight = false;
                scheduleNext(retryDelayMs());
            });
    };

    const recoverIfOverdue = () => {
        if (!requestInFlight && Date.now() - lastAttemptAt >= intervalMs) send();
    };

    // Lifecycle events recover timers after sleep, background throttling, bfcache
    // restoration, connectivity changes, and playlist-driven page reloads.
    window.addEventListener('online', () => send());
    window.addEventListener('focus', recoverIfOverdue);
    window.addEventListener('pageshow', recoverIfOverdue);
    window.addEventListener('pagehide', () => send({ preferBeacon: true }));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') recoverIfOverdue();
    });
    window.addEventListener('resize', () => {
        window.clearTimeout(window.__huginResizeHeartbeat);
        window.__huginResizeHeartbeat = window.setTimeout(() => send(), 600);
    });
    screen.orientation?.addEventListener?.('change', () => send());

    watchdog = window.setInterval(recoverIfOverdue, Math.min(15000, Math.max(5000, Math.floor(intervalMs / 3))));
    send();
})();
