(function () {
    const clockIntervals = new WeakMap();
    const rainInstances = new WeakMap();
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function updateClock(container) {
        const dateEl = container.querySelector('[data-brightsky-weather-date]');
        const timeEl = container.querySelector('[data-brightsky-weather-time]');
        if (!dateEl || !timeEl) return;

        const now = new Date();
        dateEl.textContent = now.toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        timeEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    function initClock(container) {
        if (clockIntervals.has(container)) return;
        updateClock(container);
        clockIntervals.set(container, window.setInterval(function () {
            if (!container.isConnected) {
                destroyClock(container);
                return;
            }
            updateClock(container);
        }, 30000));
    }

    function destroyClock(container) {
        const intervalId = clockIntervals.get(container);
        if (intervalId) window.clearInterval(intervalId);
        clockIntervals.delete(container);
    }

    function createRainEffect(slide, canvas) {
        const ctx = canvas.getContext('2d', {alpha: true});
        if (!ctx) return null;

        let width = 0;
        let height = 0;
        let dpr = 1;
        let drops = [];
        let frameId = 0;
        let lastTime = 0;
        let visible = true;
        let destroyed = false;

        function random(min, max) {
            return min + Math.random() * (max - min);
        }

        function maxDrops() {
            const area = width * height;
            return Math.max(16, Math.min(48, Math.round(area / 36000)));
        }

        function resetDrop(drop, initial) {
            const radius = random(3.5, 12);
            drop.x = random(width * 0.04, width * 0.96);
            drop.y = initial ? random(height * 0.02, height * 0.92) : random(-height * 0.12, height * 0.18);
            drop.radius = radius;
            drop.length = radius * random(1.45, 2.8);
            drop.alpha = random(0.08, 0.22);
            drop.vy = random(2, 10) * (radius / 8);
            drop.vx = random(-0.7, 0.7);
            drop.life = random(8, 22);
            drop.age = initial ? random(0, drop.life * 0.75) : 0;
            drop.wobble = random(0, Math.PI * 2);
        }

        function resize() {
            const rect = slide.getBoundingClientRect();
            width = Math.max(1, Math.round(rect.width));
            height = Math.max(1, Math.round(rect.height));
            dpr = Math.min(window.devicePixelRatio || 1, 1.5);
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            const target = maxDrops();
            while (drops.length < target) {
                const drop = {};
                resetDrop(drop, true);
                drops.push(drop);
            }
            if (drops.length > target) drops = drops.slice(0, target);
        }

        function drawDrop(drop) {
            const x = drop.x + Math.sin(drop.wobble + drop.age * 0.7) * 1.2;
            const y = drop.y;
            const r = drop.radius;
            const alpha = drop.alpha * Math.max(0, Math.min(1, 1 - (drop.age / drop.life) * 0.55));

            ctx.save();
            ctx.globalAlpha = alpha;
            ctx.translate(x, y);

            const gradient = ctx.createRadialGradient(-r * 0.35, -r * 0.65, r * 0.2, 0, 0, r * 1.35);
            gradient.addColorStop(0, 'rgba(255,255,255,0.95)');
            gradient.addColorStop(0.42, 'rgba(208,239,255,0.36)');
            gradient.addColorStop(1, 'rgba(104,154,180,0.06)');

            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.ellipse(0, 0, r * 0.75, drop.length, 0.08, 0, Math.PI * 2);
            ctx.fill();

            ctx.globalAlpha = alpha * 0.7;
            ctx.strokeStyle = 'rgba(255,255,255,0.45)';
            ctx.lineWidth = Math.max(0.75, r * 0.08);
            ctx.beginPath();
            ctx.ellipse(-r * 0.08, -r * 0.18, r * 0.48, drop.length * 0.72, 0.08, 0, Math.PI * 2);
            ctx.stroke();

            if (drop.age > drop.life * 0.36) {
                ctx.globalAlpha = alpha * 0.35;
                ctx.strokeStyle = 'rgba(220,245,255,0.42)';
                ctx.lineWidth = Math.max(0.65, r * 0.06);
                ctx.beginPath();
                ctx.moveTo(0, r * 0.7);
                ctx.bezierCurveTo(r * 0.2, r * 1.7, -r * 0.15, r * 2.8, r * 0.08, r * 3.7);
                ctx.stroke();
            }

            ctx.restore();
        }

        function tick(time) {
            if (destroyed) return;
            if (!slide.isConnected) {
                destroy();
                return;
            }
            if (!visible || document.hidden) {
                frameId = window.requestAnimationFrame(tick);
                return;
            }

            const delta = Math.min(64, lastTime ? time - lastTime : 16) / 1000;
            lastTime = time;
            ctx.clearRect(0, 0, width, height);

            for (const drop of drops) {
                drop.age += delta;
                drop.y += drop.vy * delta * 6;
                drop.x += drop.vx * delta * 4;
                drop.wobble += delta * 0.2;
                drawDrop(drop);
                if (drop.age >= drop.life || drop.y > height + drop.length * 4) {
                    resetDrop(drop, false);
                }
            }

            if (drops.length < maxDrops() && Math.random() < 0.05) {
                const drop = {};
                resetDrop(drop, false);
                drops.push(drop);
            }

            frameId = window.requestAnimationFrame(tick);
        }

        const resizeObserver = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(resize) : null;
        if (resizeObserver) resizeObserver.observe(slide);
        window.addEventListener('resize', resize, {passive: true});

        const intersectionObserver = typeof IntersectionObserver !== 'undefined'
            ? new IntersectionObserver(function (entries) {
                visible = entries.some(function (entry) { return entry.isIntersecting; });
            }, {threshold: 0.02})
            : null;
        if (intersectionObserver) intersectionObserver.observe(slide);

        function destroy() {
            if (destroyed) return;
            destroyed = true;
            if (frameId) window.cancelAnimationFrame(frameId);
            if (resizeObserver) resizeObserver.disconnect();
            if (intersectionObserver) intersectionObserver.disconnect();
            window.removeEventListener('resize', resize);
            ctx.clearRect(0, 0, width, height);
            rainInstances.delete(slide);
        }

        resize();
        frameId = window.requestAnimationFrame(tick);
        return {destroy};
    }

    function initRain(slide) {
        const enabled = slide.getAttribute('data-brightsky-rain-effect') === '1';
        const canvas = slide.querySelector('[data-brightsky-rain-canvas]');
        const existing = rainInstances.get(slide);
        if (!enabled || !canvas || reduceMotion) {
            if (existing) existing.destroy();
            return;
        }
        if (!existing) {
            const instance = createRainEffect(slide, canvas);
            if (instance) rainInstances.set(slide, instance);
        }
    }

    function cleanupDisconnected() {
        document.querySelectorAll('.brightsky-weather-slide').forEach(function (slide) {
            if (slide.getAttribute('data-brightsky-weather-clock') === '1') initClock(slide);
            initRain(slide);
        });
    }

    function boot() {
        cleanupDisconnected();
        const observer = new MutationObserver(cleanupDisconnected);
        observer.observe(document.documentElement, {childList: true, subtree: true, attributes: true, attributeFilter: ['data-brightsky-rain-effect']});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
