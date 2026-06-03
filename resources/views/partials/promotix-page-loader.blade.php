<div id="promotix-page-loader" class="pointer-events-none fixed inset-0 z-[250] hidden items-center justify-center bg-[#0d0d0d]/72 backdrop-blur-[2px]" aria-hidden="true" aria-live="polite">
    <div class="pointer-events-auto flex flex-col items-center gap-[14px] rounded-[12px] border border-[#6400B2]/50 bg-[#111111]/95 px-[28px] py-[22px] shadow-[0_0_40px_rgba(100,0,179,.45)]">
        <div class="h-[44px] w-[44px] animate-spin rounded-full border-[3px] border-[#6400B2]/30 border-t-[#B893D8]"></div>
        <p data-loader-msg class="text-[13px] font-medium text-white/90">Loading data…</p>
    </div>
</div>
<script>
window.promotixPageLoader = (function () {
    let visible = false;
    let shownAt = 0;
    let hideTimer = null;
    const minMs = 1200;

    function node() {
        return document.getElementById('promotix-page-loader');
    }

    return {
        show(msg) {
            clearTimeout(hideTimer);
            visible = true;
            shownAt = Date.now();
            const el = node();
            if (!el) return;
            el.classList.remove('hidden');
            el.classList.add('flex');
            el.setAttribute('aria-hidden', 'false');
            const label = el.querySelector('[data-loader-msg]');
            if (label && msg) label.textContent = msg;
        },
        hide() {
            if (!visible) return;
            visible = false;
            const wait = Math.max(0, minMs - (Date.now() - shownAt));
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                const el = node();
                if (!el) return;
                el.classList.add('hidden');
                el.classList.remove('flex');
                el.setAttribute('aria-hidden', 'true');
            }, wait);
        },
    };
})();
</script>
