@auth
<meta name="user-timezone-source" content="{{ auth()->user()->timezone_source ?? '' }}">
<script>
document.addEventListener('DOMContentLoaded', () => {
    const source = document.querySelector('meta[name="user-timezone-source"]')?.content || '';
    if (source === 'manual') {
        initHeaderTimezoneClock();
        return;
    }

    let tz = '';
    try {
        tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e) {}

    if (!tz) {
        initHeaderTimezoneClock();
        return;
    }

    fetch(@json(route('profile.timezone.sync')), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            Accept: 'application/json',
        },
        body: JSON.stringify({ timezone: tz }),
    })
        .then((response) => (response.ok ? response.json() : null))
        .then((data) => {
            // Never hard-reload the page — that loops for new users while the
            // timezone header chip is still absent from the first paint.
            if (data?.timezone) {
                const nameEl = document.getElementById('header-timezone-name');
                const clockHost = document.getElementById('header-timezone');
                if (nameEl) nameEl.textContent = data.timezone;
                if (clockHost) clockHost.dataset.timezone = data.timezone;
                if (data.label) {
                    const clockEl = document.getElementById('header-timezone-clock');
                    if (clockEl) clockEl.textContent = data.label;
                }
            }
            initHeaderTimezoneClock();
        })
        .catch(() => initHeaderTimezoneClock());
});

function initHeaderTimezoneClock() {
    const host = document.getElementById('header-timezone');
    const clockEl = document.getElementById('header-timezone-clock');
    const tz = host?.dataset?.timezone;
    if (!clockEl || !tz) {
        return;
    }

    const tick = () => {
        try {
            clockEl.textContent = new Intl.DateTimeFormat('en-US', {
                timeZone: tz,
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            }).format(new Date());
        } catch (e) {}
    };

    tick();
    window.setInterval(tick, 30000);
}
</script>
@endauth
