<style>
    #promotix-page-loader {
        pointer-events: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(8, 8, 10, 0.78);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    #promotix-page-loader.is-visible {
        display: flex;
        pointer-events: auto;
    }
    #promotix-page-loader .pmx-loader-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
        border-radius: 12px;
        border: 1px solid rgba(184, 147, 216, 0.55);
        background: rgba(17, 17, 17, 0.96);
        padding: 22px 28px;
        box-shadow: 0 0 40px rgba(100, 0, 179, 0.45);
    }
    #promotix-page-loader .pmx-loader-spin {
        width: 48px;
        height: 48px;
        border-radius: 999px;
        border: 3px solid rgba(184, 147, 216, 0.25);
        border-top-color: #B893D8;
        border-right-color: #6400B2;
        animation: pmx-loader-spin 0.75s linear infinite;
        box-shadow: 0 0 18px rgba(184, 147, 216, 0.35);
    }
    #promotix-page-loader .pmx-loader-msg {
        margin: 0;
        font-size: 13px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
    }
    @keyframes pmx-loader-spin {
        to { transform: rotate(360deg); }
    }
    html.light-mode #promotix-page-loader {
        background: rgba(247, 245, 250, 0.82);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    html.light-mode #promotix-page-loader .pmx-loader-card {
        background: rgba(255, 255, 255, 0.98);
        border-color: #d4c4e8;
        box-shadow: 0 8px 28px rgba(100, 0, 178, 0.12);
    }
    html.light-mode #promotix-page-loader .pmx-loader-msg {
        color: #2d2d3a;
    }
</style>
<div id="promotix-page-loader" aria-hidden="true" aria-live="polite">
    <div class="pmx-loader-card">
        <div class="pmx-loader-spin" aria-hidden="true"></div>
        <p data-loader-msg class="pmx-loader-msg">Loading data…</p>
    </div>
</div>
<script>
window.promotixPageLoader = (function () {
    let visible = false;
    let shownAt = 0;
    let hideTimer = null;
    let safetyTimer = null;
    const minMs = 700;

    function node() {
        return document.getElementById('promotix-page-loader');
    }

    function isInternalNavLink(a) {
        if (!a || a.hasAttribute('download') || a.target === '_blank') return false;
        if (a.hasAttribute('data-no-loader')) return false;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }
        try {
            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash === '') {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    const api = {
        show(msg) {
            clearTimeout(hideTimer);
            clearTimeout(safetyTimer);
            const el = node();
            if (!el) return;
            visible = true;
            shownAt = Date.now();
            el.classList.add('is-visible');
            el.setAttribute('aria-hidden', 'false');
            const label = el.querySelector('[data-loader-msg]');
            if (label && msg) label.textContent = msg;
            safetyTimer = setTimeout(() => api.hide(), 15000);
        },
        hide() {
            if (!visible) return;
            visible = false;
            clearTimeout(safetyTimer);
            const wait = Math.max(0, minMs - (Date.now() - shownAt));
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                const el = node();
                if (!el) return;
                el.classList.remove('is-visible');
                el.setAttribute('aria-hidden', 'true');
            }, wait);
        },
        isVisible() {
            return visible;
        },
    };

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest && e.target.closest('a[href]');
        if (!isInternalNavLink(a)) return;
        api.show('Loading…');
    }, true);

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.hasAttribute('data-no-loader') || form.getAttribute('target') === '_blank') return;
        if (form.hasAttribute('x-on:submit.prevent') || form.getAttribute('@submit.prevent') != null) return;
        api.show(form.method && form.method.toLowerCase() === 'post' ? 'Saving…' : 'Loading…');
    }, true);

    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) api.hide();
    });

    return api;
})();
</script>
