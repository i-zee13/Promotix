@php
    $active = strtolower((string) ($activeBrand ?? ''));
@endphp
<div class="mt-4 flex flex-wrap items-center justify-center gap-2" aria-label="Accepted cards">
    {{-- Mastercard --}}
    <span @class([
        'inline-flex h-8 w-[46px] items-center justify-center rounded-[5px] bg-white px-1 shadow-sm transition',
        'ring-2 ring-white scale-105' => in_array($active, ['mastercard', 'master'], true),
        'opacity-80' => $active !== '' && ! in_array($active, ['mastercard', 'master'], true),
    ]) title="Mastercard">
        <svg viewBox="0 0 48 30" class="h-5 w-8" aria-hidden="true">
            <circle cx="18" cy="15" r="10" fill="#EB001B"/>
            <circle cx="30" cy="15" r="10" fill="#F79E1B"/>
            <path d="M24 7.8a10 10 0 0 1 0 14.4 10 10 0 0 1 0-14.4z" fill="#FF5F00"/>
        </svg>
    </span>

    {{-- Visa --}}
    <span @class([
        'inline-flex h-8 w-[46px] items-center justify-center rounded-[5px] bg-[#1A1F71] px-1 shadow-sm transition',
        'ring-2 ring-white scale-105' => $active === 'visa',
        'opacity-80' => $active !== '' && $active !== 'visa',
    ]) title="Visa">
        <svg viewBox="0 0 48 16" class="h-3.5 w-9" aria-hidden="true">
            <text x="2" y="13" fill="#fff" font-size="14" font-family="Arial Black, Arial, sans-serif" font-style="italic" letter-spacing="1">VISA</text>
        </svg>
    </span>

    {{-- Amex --}}
    <span @class([
        'inline-flex h-8 w-[46px] items-center justify-center rounded-[5px] bg-[#2E77BC] px-1 shadow-sm transition',
        'ring-2 ring-white scale-105' => in_array($active, ['amex', 'american express', 'american_express'], true),
        'opacity-80' => $active !== '' && ! in_array($active, ['amex', 'american express', 'american_express'], true),
    ]) title="American Express">
        <svg viewBox="0 0 48 16" class="h-3.5 w-9" aria-hidden="true">
            <text x="1" y="13" fill="#fff" font-size="11" font-family="Arial Black, Arial, sans-serif" font-style="italic" letter-spacing="0.5">AMEX</text>
        </svg>
    </span>

    {{-- UnionPay --}}
    <span @class([
        'inline-flex h-8 w-[46px] items-center justify-center overflow-hidden rounded-[5px] bg-white shadow-sm transition',
        'ring-2 ring-white scale-105' => in_array($active, ['unionpay', 'union pay'], true),
        'opacity-80' => $active !== '' && ! in_array($active, ['unionpay', 'union pay'], true),
    ]) title="UnionPay">
        <svg viewBox="0 0 48 30" class="h-7 w-11" aria-hidden="true">
            <rect x="2" y="4" width="12" height="22" rx="1" fill="#E21836"/>
            <rect x="14" y="4" width="12" height="22" rx="1" fill="#00447C"/>
            <rect x="26" y="4" width="12" height="22" rx="1" fill="#007B84"/>
            <text x="5" y="26" fill="#fff" font-size="5.5" font-family="Arial, sans-serif" font-weight="700">UnionPay</text>
        </svg>
    </span>
</div>
<p class="mt-2 text-center text-[11px] text-white/55">Mastercard · Visa · Amex · UnionPay</p>
