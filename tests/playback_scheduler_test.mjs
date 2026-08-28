import '../public/assets/js/playback-scheduler.js';

const scheduler = globalThis.HuginPlaybackScheduler;
const same = (expected, actual, name) => {
    if (expected !== actual) throw new Error(`${name}: expected ${expected}, got ${actual}`);
    console.log(`PASS ${name}`);
};

same(0, scheduler.normalizeTimestamp('invalid'), 'invalid boundary is disabled');
same(1500, scheduler.normalizeTimestamp('1500'), 'numeric boundary is normalized');
same(250, scheduler.serverClockOffset(1250, 1000), 'server clock offset');
same(750, scheduler.delayUntil(2000, 250, 1000), 'boundary delay includes server offset');
same(0, scheduler.delayUntil(1000, 250, 1000), 'past boundary fires immediately');

delete globalThis.HuginPlaybackScheduler;
