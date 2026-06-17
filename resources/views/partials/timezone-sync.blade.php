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

    if (tz) {
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
                if (!data || data.skipped) {
                    initHeaderTimezoneClock();
                    return;
                }

                if (data.timezone && !document.getElementById('header-timezone')) {
                    window.location.reload();
                    return;
                }

                const nameEl = document.getElementById('header-timezone-name');
                const clockHost = document.getElementById('header-timezone');
                if (nameEl && data.timezone) {
                    nameEl.textContent = data.timezone;
                }
                if (clockHost && data.timezone) {
                    clockHost.dataset.timezone = data.timezone;
                }
                initHeaderTimezoneClock();
            })
            .catch(() => initHeaderTimezoneClock());
    } else {
        initHeaderTimezoneClock();
    }
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
