(() => {
    const normalizeTimestamp = value => {
        const timestamp = Number(value || 0);
        return Number.isFinite(timestamp) && timestamp > 0 ? timestamp : 0;
    };

    const serverClockOffset = (serverTimeMs, clientTimeMs = Date.now()) => {
        const server = normalizeTimestamp(serverTimeMs);
        return server > 0 ? server - Number(clientTimeMs || 0) : 0;
    };

    const delayUntil = (targetMs, offsetMs = 0, clientTimeMs = Date.now()) => {
        const target = normalizeTimestamp(targetMs);
        if (target <= 0) return 0;
        return Math.max(0, Math.ceil(target - (Number(clientTimeMs || 0) + Number(offsetMs || 0))));
    };

    globalThis.HuginPlaybackScheduler = Object.freeze({ normalizeTimestamp, serverClockOffset, delayUntil });
})();
