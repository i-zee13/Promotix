<script>
document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('right-notifications');
    const toggle = document.getElementById('rightbar-notify-toggle');
    const dot = document.getElementById('rightbar-notify-dot');
    if (!wrap) return;

    const setOpen = (open) => {
        wrap.classList.toggle('hidden', !open);
        if (open) wrap.removeAttribute('hidden');
        else wrap.setAttribute('hidden', '');
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle?.classList.toggle('is-open', open);
        if (open) toggle?.classList.add('ring-1', 'ring-white/40');
        else toggle?.classList.remove('ring-1', 'ring-white/40');
    };

    toggle?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const willOpen = wrap.hasAttribute('hidden') || wrap.classList.contains('hidden');
        setOpen(willOpen);
    });

    // Click outside closes the panel (within rightbar)
    document.addEventListener('click', (e) => {
        const root = document.getElementById('rightbar-notify-root');
        if (!root || !toggle) return;
        if (wrap.hasAttribute('hidden')) return;
        if (root.contains(e.target)) return;
        setOpen(false);
    });

    fetch('/notifications', { headers: { Accept: 'application/json' } })
        .then((res) => res.ok ? res.json() : [])
        .then((rows) => {
            const items = Array.isArray(rows) ? rows.slice(0, 5) : [];
            if (dot) {
                if (items.length) dot.removeAttribute('hidden');
                else dot.setAttribute('hidden', '');
            }
            if (!items.length) {
                wrap.innerHTML = '<div class="text-white/60">No alerts yet — traffic will appear here.</div>';
                return;
            }
            wrap.innerHTML = items.map((row) => `
                <div class="flex items-start gap-[10px] border-b border-[#a9a9a9]/70 pb-[8px] last:border-b-0">
                    <svg class="mt-[1px] h-[14px] w-[14px] shrink-0 text-white/85" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M4 6h16v12H4z"/><path stroke-width="1.7" d="M4 7l8 6 8-6"/></svg>
                    <div>
                        <p class="font-semibold text-white/90">${row.title || 'Alert'}</p>
                        <p class="mt-[2px] leading-snug">${row.body || ''}</p>
                    </div>
                </div>
            `).join('');
        })
        .catch(() => {
            wrap.innerHTML = '<div class="text-white/60">Could not load notifications.</div>';
        });
});
</script>
