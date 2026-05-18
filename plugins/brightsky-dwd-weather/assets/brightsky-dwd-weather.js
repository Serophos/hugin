(function () {
    const clockIntervals = new WeakMap();
    const rainInstances = new WeakMap();
    const lightningInstances = new WeakMap();
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
            return Math.max(36, Math.min(110, Math.round(area / 19000)));
        }

        function resetDrop(drop, initial) {
            const large = Math.random() > 0.72;
            const radius = large ? random(7.5, 18) : random(2.5, 8.5);
            drop.x = random(width * 0.025, width * 0.975);
            drop.y = initial ? random(height * 0.015, height * 0.96) : random(-height * 0.16, height * 0.12);
            drop.radius = radius;
            drop.stretch = large ? random(1.0, 1.35) : random(0.86, 1.16);
            drop.alpha = large ? random(0.34, 0.58) : random(0.24, 0.44);
            drop.vy = (large ? random(1.4, 5.6) : random(0.35, 2.4)) * (radius / 8);
            drop.vx = random(-0.32, 0.32);
            drop.life = large ? random(18, 42) : random(14, 32);
            drop.age = initial ? random(0, drop.life * 0.82) : 0;
            drop.wobble = random(0, Math.PI * 2);
            drop.trail = large && Math.random() > 0.42;
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
            const x = drop.x + Math.sin(drop.wobble + drop.age * 0.65) * Math.min(1.8, drop.radius * 0.16);
            const y = drop.y;
            const r = drop.radius;
            const ry = r * drop.stretch;
            const fade = Math.max(0, Math.min(1, 1 - (drop.age / drop.life) * 0.42));
            const alpha = drop.alpha * fade;
            const tilt = Math.sin(drop.wobble) * 0.12;

            ctx.save();
            ctx.translate(x, y);
            ctx.rotate(tilt);

            if (drop.trail && drop.age > drop.life * 0.16) {
                const trailLength = Math.min(r * 8.5, 74);
                const trailGradient = ctx.createLinearGradient(0, ry * 0.7, 0, ry * 0.7 + trailLength);
                trailGradient.addColorStop(0, 'rgba(255,255,255,0.18)');
                trailGradient.addColorStop(0.45, 'rgba(190,225,238,0.08)');
                trailGradient.addColorStop(1, 'rgba(255,255,255,0)');
                ctx.globalAlpha = alpha * 0.8;
                ctx.strokeStyle = trailGradient;
                ctx.lineWidth = Math.max(1, r * 0.18);
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(r * 0.08, ry * 0.72);
                ctx.bezierCurveTo(-r * 0.18, ry + trailLength * 0.28, r * 0.2, ry + trailLength * 0.62, 0, ry + trailLength);
                ctx.stroke();
            }

            ctx.globalAlpha = alpha * 0.68;
            ctx.fillStyle = 'rgba(0,22,26,0.42)';
            ctx.beginPath();
            ctx.ellipse(r * 0.16, r * 0.22, r * 0.88, ry * 0.88, 0, 0, Math.PI * 2);
            ctx.fill();

            const glass = ctx.createRadialGradient(-r * 0.38, -ry * 0.46, Math.max(1, r * 0.08), 0, 0, r * 1.28);
            glass.addColorStop(0, 'rgba(255,255,255,0.98)');
            glass.addColorStop(0.18, 'rgba(255,255,255,0.36)');
            glass.addColorStop(0.48, 'rgba(204,237,247,0.14)');
            glass.addColorStop(0.74, 'rgba(44,83,92,0.18)');
            glass.addColorStop(1, 'rgba(4,28,34,0.34)');

            ctx.globalAlpha = alpha;
            ctx.fillStyle = glass;
            ctx.beginPath();
            ctx.ellipse(0, 0, r * 0.86, ry, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.globalAlpha = alpha * 0.95;
            ctx.lineWidth = Math.max(0.9, r * 0.12);
            ctx.strokeStyle = 'rgba(5,34,39,0.62)';
            ctx.beginPath();
            ctx.ellipse(0, 0, r * 0.86, ry, 0, Math.PI * 0.12, Math.PI * 1.36);
            ctx.stroke();

            ctx.globalAlpha = alpha * 0.86;
            ctx.strokeStyle = 'rgba(255,255,255,0.72)';
            ctx.lineWidth = Math.max(0.75, r * 0.08);
            ctx.beginPath();
            ctx.ellipse(-r * 0.14, -ry * 0.13, r * 0.62, ry * 0.72, 0, Math.PI * 1.08, Math.PI * 1.94);
            ctx.stroke();

            ctx.globalAlpha = Math.min(1, alpha * 1.25);
            ctx.fillStyle = 'rgba(255,255,255,0.9)';
            ctx.beginPath();
            ctx.ellipse(-r * 0.36, -ry * 0.45, Math.max(1, r * 0.16), Math.max(1, r * 0.12), -0.45, 0, Math.PI * 2);
            ctx.fill();
            if (r > 6) {
                ctx.globalAlpha = alpha * 0.7;
                ctx.beginPath();
                ctx.ellipse(r * 0.26, ry * 0.34, Math.max(0.8, r * 0.12), Math.max(0.8, r * 0.1), -0.4, 0, Math.PI * 2);
                ctx.fill();
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
                drop.y += drop.vy * delta * 8;
                drop.x += drop.vx * delta * 5;
                drop.wobble += delta * 0.2;
                drawDrop(drop);
                if (drop.age >= drop.life || drop.y > height + drop.radius * drop.stretch * 8) {
                    resetDrop(drop, false);
                }
            }

            if (drops.length < maxDrops() && Math.random() < 0.08) {
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

    function createLightningEffect(slide, canvas) {
        const ctx = canvas.getContext('2d', {alpha: true});
        if (!ctx) return null;

        let width = 0;
        let height = 0;
        let dpr = 1;
        let frameId = 0;
        let timerId = 0;
        let strike = null;
        let visible = true;
        let destroyed = false;

        function random(min, max) {
            return min + Math.random() * (max - min);
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
        }

        function makeSegment(start, end, roughness) {
            const points = [start];
            const distance = Math.hypot(end.x - start.x, end.y - start.y);
            const steps = Math.max(5, Math.min(18, Math.round(distance / 54)));
            for (let i = 1; i < steps; i += 1) {
                const t = i / steps;
                const x = start.x + (end.x - start.x) * t;
                const y = start.y + (end.y - start.y) * t;
                const normalX = -(end.y - start.y) / Math.max(distance, 1);
                const normalY = (end.x - start.x) / Math.max(distance, 1);
                const offset = random(-roughness, roughness) * (1 - Math.abs(0.5 - t) * 0.72);
                points.push({x: x + normalX * offset, y: y + normalY * offset});
            }
            points.push(end);
            return points;
        }

        function makeBranch(origin, angle, length, depth) {
            const end = {
                x: origin.x + Math.cos(angle) * length,
                y: origin.y + Math.sin(angle) * length
            };
            const points = makeSegment(origin, end, Math.max(8, length * 0.13));
            const branches = [];
            if (depth < 2 && length > 70 && Math.random() > 0.38) {
                const branchOrigin = points[Math.floor(random(1, points.length - 1))];
                branches.push(makeBranch(branchOrigin, angle + random(-0.95, 0.95), length * random(0.32, 0.58), depth + 1));
            }
            return {points, branches, width: Math.max(0.7, 2.8 - depth * 0.9)};
        }

        function makeStrike() {
            const start = {x: random(width * 0.1, width * 0.9), y: random(-height * 0.03, height * 0.1)};
            const side = Math.random() > 0.5 ? 1 : -1;
            const end = {
                x: Math.max(width * 0.04, Math.min(width * 0.96, start.x + side * random(width * 0.16, width * 0.42))),
                y: random(height * 0.56, height * 0.96)
            };
            const main = makeBranch(start, Math.atan2(end.y - start.y, end.x - start.x), Math.hypot(end.x - start.x, end.y - start.y), 0);
            const branches = [];
            const branchCount = Math.round(random(4, 9));
            for (let i = 0; i < branchCount; i += 1) {
                const origin = main.points[Math.floor(random(1, main.points.length - 2))];
                const angle = random(-Math.PI * 0.95, Math.PI * 0.25) + (side > 0 ? 0 : Math.PI);
                branches.push(makeBranch(origin, angle, random(width * 0.08, width * 0.28), 1));
            }
            return {
                branches: [main].concat(branches),
                born: performance.now(),
                duration: random(320, 620),
                flash: random(0.18, 0.34)
            };
        }

        function drawPath(points, widthValue, color, alpha) {
            if (points.length < 2) return;
            ctx.globalAlpha = alpha;
            ctx.strokeStyle = color;
            ctx.lineWidth = widthValue;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            for (let i = 1; i < points.length; i += 1) {
                ctx.lineTo(points[i].x, points[i].y);
            }
            ctx.stroke();
        }

        function drawBranch(branch, fade) {
            drawPath(branch.points, branch.width * 8.5, 'rgba(75,130,255,0.34)', fade * 0.42);
            drawPath(branch.points, branch.width * 4.2, 'rgba(152,186,255,0.68)', fade * 0.56);
            drawPath(branch.points, branch.width * 1.65, 'rgba(220,230,255,0.96)', fade * 0.92);
            drawPath(branch.points, Math.max(0.8, branch.width * 0.52), 'rgba(255,255,255,1)', fade);
            for (const child of branch.branches) drawBranch(child, fade * 0.76);
        }

        function draw(time) {
            if (destroyed) return;
            if (!slide.isConnected) {
                destroy();
                return;
            }
            ctx.clearRect(0, 0, width, height);

            if (strike && visible && !document.hidden) {
                const age = time - strike.born;
                if (age <= strike.duration) {
                    const pulse = age < 90 || (age > 150 && age < 220) ? 1 : 0.55;
                    const fade = Math.max(0, 1 - age / strike.duration) * pulse;
                    ctx.globalAlpha = strike.flash * fade;
                    ctx.fillStyle = 'rgba(215,232,255,1)';
                    ctx.fillRect(0, 0, width, height);
                    for (const branch of strike.branches) drawBranch(branch, fade);
                } else {
                    strike = null;
                }
            }

            frameId = window.requestAnimationFrame(draw);
        }

        function scheduleStrike(initial) {
            if (destroyed) return;
            const delay = initial ? random(450, 1200) : random(2600, 7600);
            timerId = window.setTimeout(function () {
                if (!destroyed && visible && !document.hidden) {
                    strike = makeStrike();
                }
                scheduleStrike(false);
            }, delay);
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
            if (timerId) window.clearTimeout(timerId);
            if (resizeObserver) resizeObserver.disconnect();
            if (intersectionObserver) intersectionObserver.disconnect();
            window.removeEventListener('resize', resize);
            ctx.clearRect(0, 0, width, height);
            lightningInstances.delete(slide);
        }

        resize();
        scheduleStrike(true);
        frameId = window.requestAnimationFrame(draw);
        return {destroy};
    }

    function initLightning(slide) {
        const enabled = slide.getAttribute('data-brightsky-lightning-effect') === '1';
        const canvas = slide.querySelector('[data-brightsky-lightning-canvas]');
        const existing = lightningInstances.get(slide);
        if (!enabled || !canvas || reduceMotion) {
            if (existing) existing.destroy();
            return;
        }
        if (!existing) {
            const instance = createLightningEffect(slide, canvas);
            if (instance) lightningInstances.set(slide, instance);
        }
    }

    function cleanupDisconnected() {
        document.querySelectorAll('.brightsky-weather-slide').forEach(function (slide) {
            if (slide.getAttribute('data-brightsky-weather-clock') === '1') initClock(slide);
            initRain(slide);
            initLightning(slide);
        });
    }

    function boot() {
        cleanupDisconnected();
        const observer = new MutationObserver(cleanupDisconnected);
        observer.observe(document.documentElement, {childList: true, subtree: true, attributes: true, attributeFilter: ['data-brightsky-rain-effect', 'data-brightsky-lightning-effect']});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
