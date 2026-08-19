<style>
    /* Page-local: Tailwind Forms makes every select display:block;width:100%. Vite bundle may lag deploy. */
    .adv-pager {
        display: flex !important;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px 16px;
        border-top: 1px solid rgba(103, 6, 179, 0.4);
        padding: 10px 14px;
        font-size: 11px;
        color: #a9a9a9;
    }
    .adv-pager__label {
        flex: 1 1 auto;
        min-width: 0;
        line-height: 28px;
        white-space: nowrap;
    }
    .adv-pager__controls {
        display: flex !important;
        flex-wrap: nowrap;
        align-items: center;
        gap: 8px;
        margin-left: auto;
        flex: 0 0 auto;
        width: auto !important;
        max-width: 100%;
    }
    .adv-pager__pages {
        display: flex !important;
        flex-wrap: nowrap;
        align-items: center;
        gap: 4px;
    }
    .adv-pager__btn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        padding: 0 8px;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: transparent;
        color: #d4d4d4;
        font-size: 11px;
        line-height: 1;
        cursor: pointer;
    }
    .adv-pager__btn.is-active {
        background: #6400B2;
        border-color: #6400B2;
        color: #fff;
    }
    .adv-pager__btn:disabled {
        opacity: 0.35;
        cursor: default;
    }
    .adv-pager select,
    .adv-pager__select {
        display: inline-block !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        width: 108px !important;
        min-width: 108px !important;
        max-width: 108px !important;
        height: 28px !important;
        flex: 0 0 108px !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        border-radius: 6px !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        background-color: #161616 !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a9a9a9'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 6px center !important;
        background-size: 12px !important;
        color: #d4d4d4 !important;
        font-size: 11px !important;
        line-height: 26px !important;
        padding: 0 22px 0 8px !important;
    }
    html.light-mode .adv-pager { color: #5c5470; }
    html.light-mode .adv-pager__btn { color: #2d2d3a; border-color: #d4c4e8; }
    html.light-mode .adv-pager select,
    html.light-mode .adv-pager__select {
        background-color: #fff !important;
        color: #2d2d3a !important;
        border-color: #d4c4e8 !important;
    }
</style>
