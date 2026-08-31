@props([
    'maxWidth' => 'max-w-[1001px]',
    'innerWidth' => 'max-w-md',
    'minHeight' => 'min-h-[520px]',
    'padding' => 'px-6 py-12 sm:px-12 sm:py-16 md:px-20 md:py-24',
])

{{--
    Solid brand auth card (no gradient).
    Colors come from --brand-primary (Super Admin → Branding).
--}}
<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8" style="background:var(--brand-background,#0D0D0D);">
    <div {{ $attributes->class([
        'auth-card-panel',
        'w-full',
        $maxWidth,
        $minHeight,
        'rounded-[15px]',
        'border',
        'border-white/35',
        'flex',
        'items-center',
        'justify-center',
        $padding,
    ]) }}
        style="background:var(--brand-primary,#FF6600);box-shadow:0 1px 0 0 rgba(255,255,255,0.25),0 25px 60px -20px rgba(var(--brand-primary-rgb,255,102,0),0.55);"
    >
        <div class="w-full {{ $innerWidth }} mx-auto">
            {{ $slot }}
        </div>
    </div>
</div>
