@php $item = $item ?? 'row'; @endphp
<template x-if="col.key === 'country'">
    <span class="adv-cell-flag">
        <img x-show="countryFlagUrl({{ $item }}.country)"
             :src="countryFlagUrl({{ $item }}.country)"
             alt=""
             width="16"
             height="12"
             loading="lazy"
             decoding="async"
             referrerpolicy="no-referrer"
             @@error="$el.style.display='none'">
        <span x-text="countryCode({{ $item }}.country) || {{ $item }}.country || '—'"></span>
    </span>
</template>
<template x-if="col.key === 'intel_risk_score'">
    <span class="adv-risk-ring" :style="riskRingStyle({{ $item }})"><span x-text="score100({{ $item }})"></span></span>
</template>
<template x-if="col.key === 'intel_risk_level'">
    <span class="adv-risk-level" :class="riskLevelClass({{ $item }})" x-text="riskLevelLabel({{ $item }})"></span>
</template>
<template x-if="col.key === 'action_taken'">
    <span class="adv-action-badge" :class="actionBadgeClass({{ $item }})" x-text="actionBadgeLabel({{ $item }})"></span>
</template>
<template x-if="col.key === 'status'">
    <span class="adv-status-dot" :class="statusDotClass({{ $item }})" :title="actionBadgeLabel({{ $item }})"></span>
</template>
<template x-if="col.key === 'session_recording'">
    <span class="flex items-center justify-center">
        <button type="button" x-show="{{ $item }}.has_session_recording" @click.stop="openRecording({{ $item }})" class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-full bg-[#FF6600] text-white hover:bg-[#ff8533]" title="Watch session recording">
            <svg class="h-[11px] w-[11px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </button>
        <span x-show="!{{ $item }}.has_session_recording" class="text-[#8c8787]">—</span>
    </span>
</template>
<template x-if="col.key === 'source_platform'">
    <span class="tc-source-cell" :title="cellValue({{ $item }}, 'source_platform')">
        <span class="tc-source-cell__icon" :class="'is-' + sourcePlatformKind({{ $item }}.source_platform)" aria-hidden="true">
            <template x-if="sourcePlatformKind({{ $item }}.source_platform) === 'google'">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="#EA4335" d="M12 10.2v3.6h5.1c-.2 1.2-.9 2.2-1.9 2.9l3.1 2.4c1.8-1.7 2.9-4.1 2.9-7 0-.7-.1-1.3-.2-1.9H12z"/><path fill="#34A853" d="M6.6 14.3l-.9.7-2.5 1.9C4.8 19.7 8.1 22 12 22c2.7 0 4.9-.9 6.5-2.4l-3.1-2.4c-.9.6-2 .9-3.4.9-2.6 0-4.8-1.8-5.4-4.1z"/><path fill="#4A90E2" d="M3.2 7.1C2.4 8.6 2 10.2 2 12s.4 3.4 1.2 4.9l3.4-2.6C6.2 13.4 6 12.7 6 12s.2-1.4.6-2.3L3.2 7.1z"/><path fill="#FBBC05" d="M12 6c1.5 0 2.8.5 3.8 1.5l2.8-2.8C16.9 2.9 14.7 2 12 2 8.1 2 4.8 4.3 3.2 7.1l3.4 2.6C7.2 7.8 9.4 6 12 6z"/></svg>
            </template>
            <template x-if="sourcePlatformKind({{ $item }}.source_platform) === 'facebook'">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="#1877F2" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.41 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.79-4.7 4.54-4.7 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.5 0-1.96.93-1.96 1.89v2.27h3.34l-.53 3.49h-2.81V24C19.61 23.09 24 18.1 24 12.07z"/></svg>
            </template>
            <template x-if="sourcePlatformKind({{ $item }}.source_platform) === 'instagram'">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="#E4405F" d="M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm5 5.2A4.8 4.8 0 1016.8 12 4.8 4.8 0 0012 7.2zm6.1-.9a1.1 1.1 0 11-1.1-1.1 1.1 1.1 0 011.1 1.1zM12 9.4A2.6 2.6 0 1114.6 12 2.6 2.6 0 0112 9.4z"/></svg>
            </template>
            <template x-if="sourcePlatformKind({{ $item }}.source_platform) === 'yahoo'">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="#6001D2" d="M12.9 11.5L17.8 2h-3.1l-3.2 6.1L8.3 2H5.1l4.9 9.5V22h3V11.5z"/></svg>
            </template>
            <template x-if="sourcePlatformKind({{ $item }}.source_platform) === 'bing'">
                <svg viewBox="0 0 24 24" width="14" height="14"><path fill="#00809D" d="M5 3l5.2 1.8v12.3L14.8 15l2.2 1.1L10.2 22 5 19.2V3z"/></svg>
            </template>
            <template x-if="['direct','organic','paid','social','link','linkedin','twitter'].includes(sourcePlatformKind({{ $item }}.source_platform))">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            </template>
        </span>
        <span class="tc-source-cell__label" x-text="cellValue({{ $item }}, 'source_platform')"></span>
    </span>
</template>
<template x-if="col.key === 'page_flow'">
    <span class="tc-flow-cell" :title="cellValue({{ $item }}, 'page_flow')">
        <template x-for="(part, idx) in pageFlowParts({{ $item }})" :key="'flow-' + idx + '-' + part">
            <span class="tc-flow-cell__seg">
                <span class="tc-flow-cell__path" x-text="part"></span>
                <span class="tc-flow-cell__arrow" x-show="idx < pageFlowParts({{ $item }}).length - 1">-></span>
            </span>
        </template>
        <span x-show="!pageFlowParts({{ $item }}).length" class="text-[#8c8787]">—</span>
    </span>
</template>
<template x-if="col.key === 'event_actions'">
    <span class="tc-events-cell">
        <template x-for="ev in eventActionRows({{ $item }})" :key="ev.key">
            <span class="tc-events-cell__row" x-text="ev.key + ' (' + ev.count + ')'"></span>
        </template>
        <span x-show="!eventActionRows({{ $item }}).length" class="text-[#8c8787]">—</span>
    </span>
</template>
<template x-if="col.key === 'entry_time' || col.key === 'exit_time'">
    <span class="tc-datetime-cell">
        <span class="tc-datetime-cell__date" x-text="cellValue({{ $item }}, col.key)"></span>
        <span class="tc-datetime-cell__time" x-text="cellValue({{ $item }}, col.key === 'entry_time' ? 'entry_clock' : 'exit_clock')"></span>
    </span>
</template>
<template x-if="col.key === 'cta_clicks' || col.key === 'tel_clicks' || col.key === 'form_starts' || col.key === 'form_submits' || col.key === 'form_fills' || col.key === 'add_to_cart' || col.key === 'checkout'">
    <span>
        <button
            type="button"
            x-show="Number(col.key === 'form_fills' ? ({{ $item }}.form_fills ?? {{ $item }}.form_submits || 0) : ({{ $item }}[col.key] || 0)) > 0"
            @click.stop="openEventDrilldown({{ $item }}, col.key === 'form_fills' ? 'form_submits' : col.key)"
            class="font-medium text-[#FF6600] hover:underline"
            x-text="cellValue({{ $item }}, col.key)"
        ></button>
        <span x-show="Number(col.key === 'form_fills' ? ({{ $item }}.form_fills ?? {{ $item }}.form_submits || 0) : ({{ $item }}[col.key] || 0)) === 0" class="text-[#8c8787]">0</span>
    </span>
</template>
<template x-if="col.key === 'purchase'">
    <span>
        <button
            type="button"
            x-show="String({{ $item }}.purchase || '').toLowerCase() === 'yes' || Number({{ $item }}.purchase || 0) > 0"
            @click.stop="openEventDrilldown({{ $item }}, 'purchase')"
            class="font-medium text-[#FF6600] hover:underline"
            x-text="cellValue({{ $item }}, 'purchase')"
        ></button>
        <span x-show="!(String({{ $item }}.purchase || '').toLowerCase() === 'yes' || Number({{ $item }}.purchase || 0) > 0)" class="text-[#8c8787]" x-text="cellValue({{ $item }}, 'purchase')"></span>
    </span>
</template>
<template x-if="col.key === 'session_id'">
    <button type="button" class="truncate text-left font-medium text-[#FF6600] hover:underline" @click.stop="openJourneyDrawer({{ $item }})" :title="cellValue({{ $item }}, 'session_id')" x-text="cellValue({{ $item }}, 'session_id')"></button>
</template>
<template x-if="!isRichCol(col.key)">
    <span class="block truncate" :class="col.key === 'ip' && 'font-medium'" :title="cellValue({{ $item }}, col.key)" x-text="cellValue({{ $item }}, col.key)"></span>
</template>
