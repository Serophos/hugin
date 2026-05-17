(function () {
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

    function boot() {
        document.querySelectorAll('.brightsky-weather-slide[data-brightsky-weather-clock="1"]').forEach(function (container) {
            updateClock(container);
            window.setInterval(function () { updateClock(container); }, 30000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
