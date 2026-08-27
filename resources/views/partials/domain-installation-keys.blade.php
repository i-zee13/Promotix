{{-- Shared Domain / Secret / Auth key rows (Finish Setup + WordPress tab). --}}
@php
    /** @var \App\Models\Domain $domain */
    $hostname = $domain->hostname ?? 'domain';
    $showHeading = $showHeading ?? true;
    $copyAllAction = $copyAllAction ?? 'copyAllKeys()';
@endphp
@if ($showHeading)
    <div class="mt-[16px] flex flex-wrap items-center justify-between gap-[10px]">
        <p class="figma-domain-setup__instruction-step mb-0">Installation Keys for ({{ $hostname }})</p>
        <button type="button" class="figma-domain-setup__copy-all" @click="{{ $copyAllAction }}">
            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Copy all
        </button>
    </div>
@endif
@foreach ([
    ['Domain Key', $domain->domain_key],
    ['Secret key', $domain->secret_key],
    ['Authentication Key', $domain->authentication_key],
] as [$label, $value])
    <div class="figma-domain-setup__key-row">
        <span class="figma-domain-setup__key-label">{{ $label }}</span>
        <div class="figma-domain-setup__dotted-box min-h-[44px]">{{ $value }}</div>
        <button type="button" class="figma-domain-setup__copy-link shrink-0" @click="copyText(@js($value))">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Copy
        </button>
    </div>
@endforeach
