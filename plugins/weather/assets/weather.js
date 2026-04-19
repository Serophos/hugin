(function () {
    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function updateClock(container) {
        const now = new Date();
        const dateEl = container.querySelector('.weather-date');
        const timeEl = container.querySelector('.weather-time');
        if (!dateEl || !timeEl) return;

        dateEl.textContent = now.toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        timeEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    function boot() {
        document.querySelectorAll('[data-weather-clock]').forEach(function (container) {
            updateClock(container);
            window.setInterval(function () { updateClock(container); }, 1000 * 30);
        });

        document.querySelectorAll('.weather-slide[data-weather-refresh-seconds]').forEach(function (el) {
            const seconds = parseInt(el.getAttribute('data-weather-refresh-seconds') || '0', 10);
            if (seconds > 0) {
                window.setTimeout(function () { window.location.reload(); }, seconds * 1000);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
