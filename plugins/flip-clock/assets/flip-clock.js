(function () {
    const roots = new Map();
    let observer = null;
    let listenersBound = false;
    let minuteFormatter = null;
    let secondFormatter = null;
    let numberFormatter = null;

    function getFormatter(showSeconds) {
        if (showSeconds && secondFormatter) return secondFormatter;
        if (!showSeconds && minuteFormatter) return minuteFormatter;

        try {
            const formatter = new Intl.DateTimeFormat(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                second: showSeconds ? '2-digit' : undefined,
                hourCycle: 'h23'
            });
            if (showSeconds) {
                secondFormatter = formatter;
            } else {
                minuteFormatter = formatter;
            }
            return formatter;
        } catch (error) {
            return null;
        }
    }

    function getNumberFormatter() {
        if (!numberFormatter) {
            try {
                numberFormatter = new Intl.NumberFormat(undefined, {
                    minimumIntegerDigits: 2,
                    maximumFractionDigits: 0,
                    useGrouping: false
                });
            } catch (error) {
                numberFormatter = null;
            }
        }

        return numberFormatter;
    }

    function padNumber(value) {
        const nf = getNumberFormatter();
        if (nf) {
            return nf.format(value);
        }

        return String(value).padStart(2, '0');
    }

    function fallbackClockParts(date, showSeconds) {
        const hour = padNumber(date.getHours());
        const minute = padNumber(date.getMinutes());
        const second = padNumber(date.getSeconds());
        const label = showSeconds ? hour + ':' + minute + ':' + second : hour + ':' + minute;

        return {
            digits: (showSeconds ? hour + minute + second : hour + minute).split(''),
            separator: ':',
            label
        };
    }

    function getClockParts(date, showSeconds) {
        const fallback = fallbackClockParts(date, showSeconds);
        const dtf = getFormatter(showSeconds);
        if (!dtf || !dtf.formatToParts) {
            return fallback;
        }

        const parts = dtf.formatToParts(date);
        const hour = parts.find(part => part.type === 'hour')?.value || '';
        const minute = parts.find(part => part.type === 'minute')?.value || '';
        const second = parts.find(part => part.type === 'second')?.value || '';
        const literals = parts.filter(part => part.type === 'literal').map(part => part.value).filter(Boolean);
        const separator = literals[0] || ':';
        const hourDigits = Array.from(hour).slice(-2);
        const minuteDigits = Array.from(minute).slice(-2);
        const secondDigits = Array.from(second).slice(-2);

        if (hourDigits.length !== 2 || minuteDigits.length !== 2 || (showSeconds && secondDigits.length !== 2)) {
            return fallback;
        }

        return {
            digits: showSeconds ? hourDigits.concat(minuteDigits, secondDigits) : hourDigits.concat(minuteDigits),
            separator,
            label: showSeconds ? hour + separator + minute + (literals[1] || separator) + second : hour + separator + minute
        };
    }

    function isMotionReduced() {
        return Boolean(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function isVisibleSlide(root) {
        const slide = root.closest('.slide');
        return !slide || slide.classList.contains('is-active');
    }

    function showSecondsFor(root) {
        return root.dataset.flipShowSeconds === '1';
    }

    function msUntilNextTick(showSeconds) {
        const now = new Date();
        if (showSeconds) {
            return Math.max(80, 1000 - now.getMilliseconds() + 30);
        }

        const msIntoMinute = (now.getSeconds() * 1000) + now.getMilliseconds();
        return Math.max(250, 60000 - msIntoMinute + 80);
    }

    function setDigit(digit, value, animate) {
        const previous = digit.dataset.value || '';
        if (previous === value) {
            return;
        }

        const top = digit.querySelector('[data-flip-top]');
        const bottom = digit.querySelector('[data-flip-bottom]');
        const foldTop = digit.querySelector('[data-flip-fold-top]');
        const foldBottom = digit.querySelector('[data-flip-fold-bottom]');

        if (!top || !bottom || !foldTop || !foldBottom) {
            return;
        }

        if (digit.__flipClockTimers) {
            digit.__flipClockTimers.forEach(function (timer) {
                window.clearTimeout(timer);
            });
        }
        digit.__flipClockTimers = [];

        foldTop.textContent = previous || value;
        foldBottom.textContent = value;
        top.textContent = value;
        bottom.textContent = animate && previous !== '' && !isMotionReduced() ? previous : value;
        digit.dataset.value = value;

        digit.classList.remove('is-flipping');
        if (!animate || previous === '' || isMotionReduced()) {
            return;
        }

        void digit.offsetWidth;
        digit.classList.add('is-flipping');
        digit.__flipClockTimers.push(window.setTimeout(function () {
            bottom.textContent = value;
        }, 560));
        digit.__flipClockTimers.push(window.setTimeout(function () {
            bottom.textContent = value;
            digit.classList.remove('is-flipping');
            digit.__flipClockTimers = [];
        }, 700));
    }

    function update(root, animate) {
        const digits = Array.from(root.querySelectorAll('[data-flip-digit]'));
        const separators = Array.from(root.querySelectorAll('[data-flip-separator]'));
        const clock = root.querySelector('.flip-clock');
        const showSeconds = showSecondsFor(root);
        const parts = getClockParts(new Date(), showSeconds);
        if (digits.length !== parts.digits.length) {
            return;
        }

        parts.digits.forEach(function (value, index) {
            setDigit(digits[index], value, animate && isVisibleSlide(root));
        });

        separators.forEach(function (separator) {
            separator.textContent = parts.separator;
        });
        if (clock) {
            clock.setAttribute('aria-label', parts.label);
        }
    }

    function stop(root) {
        const controller = roots.get(root);
        if (!controller) {
            return;
        }

        window.clearTimeout(controller.timer);
        roots.delete(root);
        root.removeAttribute('data-flip-clock-ready');
    }

    function schedule(root) {
        const controller = roots.get(root);
        if (!controller) {
            return;
        }

        window.clearTimeout(controller.timer);
        controller.timer = window.setTimeout(function () {
            if (!document.documentElement.contains(root)) {
                stop(root);
                return;
            }

            update(root, true);
            schedule(root);
        }, msUntilNextTick(showSecondsFor(root)));
    }

    function init(root) {
        if (roots.has(root)) {
            update(root, false);
            return;
        }

        roots.set(root, { timer: 0 });
        root.setAttribute('data-flip-clock-ready', '1');
        update(root, false);
        schedule(root);
    }

    function boot() {
        document.querySelectorAll('[data-flip-clock]').forEach(init);
        bindGlobalListeners();
        watchForRemoval();
    }

    function refreshAll(animate) {
        roots.forEach(function (controller, root) {
            if (!document.documentElement.contains(root)) {
                stop(root);
                return;
            }
            update(root, animate);
            schedule(root);
        });
    }

    function bindGlobalListeners() {
        if (listenersBound) {
            return;
        }

        listenersBound = true;
        window.addEventListener('focus', function () { refreshAll(false); });
        window.addEventListener('pageshow', function () {
            boot();
            refreshAll(false);
        });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                refreshAll(false);
            }
        });
        window.addEventListener('pagehide', function () {
            roots.forEach(function (controller, root) {
                stop(root);
            });
        });
    }

    function watchForRemoval() {
        if (observer || !window.MutationObserver || !document.body) {
            return;
        }

        observer = new MutationObserver(function () {
            roots.forEach(function (controller, root) {
                if (!document.documentElement.contains(root)) {
                    stop(root);
                }
            });
            document.querySelectorAll('[data-flip-clock]:not([data-flip-clock-ready])').forEach(init);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
