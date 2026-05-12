@props([
    'maxWidth' => 'max-w-[1001px]',
    'innerWidth' => 'max-w-md',
    'minHeight' => 'min-h-[520px]',
    'padding' => 'px-6 py-12 sm:px-12 sm:py-16 md:px-20 md:py-24',
])

{{--
    Solid purple auth card (no gradient).
    Reference (Figma): #6400B3, 15px radius, 1px white/35% border, 1px white/25% drop highlight.
--}}
<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-[#0D0D0D]">
    <div {{ $attributes->class([
        'w-full',
        $maxWidth,
        $minHeight,
        'rounded-[15px]',
        'border',
        'border-white/35',
        'bg-[#6400B3]',
        'shadow-[0_1px_0_0_rgba(255,255,255,0.25),0_25px_60px_-20px_rgba(100,0,179,0.55)]',
        'flex',
        'items-center',
        'justify-center',
        $padding,
    ]) }}>
        <div class="w-full {{ $innerWidth }} mx-auto">
            {{ $slot }}
        </div>
    </div>
</div>
