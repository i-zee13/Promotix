<script>
document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('right-notifications');
    if (!wrap) return;

    fetch('/notifications', { headers: { Accept: 'application/json' } })
        .then((res) => res.ok ? res.json() : [])
        .then((rows) => {
            const items = Array.isArray(rows) ? rows.slice(0, 5) : [];
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
