@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'figma-sa-card rounded-[10px] border border-white/15 bg-[#151515] p-[18px]']) }}>
    @if ($title)
        <h2 class="text-[16px] font-semibold text-white">{{ $title }}</h2>
    @endif
    @if ($subtitle)
        <p class="mt-[4px] text-[12px] text-[#a9a9a9]">{{ $subtitle }}</p>
    @endif
    <div class="{{ $title || $subtitle ? 'mt-[14px]' : '' }}">
        {{ $slot }}
    </div>
</div>
