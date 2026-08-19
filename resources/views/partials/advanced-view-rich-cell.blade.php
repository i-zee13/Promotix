@php $item = $item ?? 'row'; @endphp
<template x-if="col.key === 'country'">
    <span class="adv-cell-flag">
        <img x-show="countryFlagUrl({{ $item }}.country)" :src="countryFlagUrl({{ $item }}.country)" alt="" width="16" height="12">
        <span x-text="countryCode({{ $item }}.country)"></span>
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
        <button type="button" x-show="{{ $item }}.has_session_recording" @click.stop="openRecording({{ $item }})" class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-full bg-[#6400B2] text-white hover:bg-[#7B13C8]" title="Watch session recording">
            <svg class="h-[11px] w-[11px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </button>
        <span x-show="!{{ $item }}.has_session_recording" class="text-[#8c8787]">—</span>
    </span>
</template>
<template x-if="!isRichCol(col.key)">
    <span class="block truncate" :class="col.key === 'ip' && 'font-medium'" :title="cellValue({{ $item }}, col.key)" x-text="cellValue({{ $item }}, col.key)"></span>
</template>
