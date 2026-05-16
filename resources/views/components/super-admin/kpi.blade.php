@props(['label', 'value', 'hint' => null])

<article class="figma-sa-kpi rounded-[10px] border border-white/25 bg-[#6400B2] p-[16px] shadow-[0_0_18px_rgba(100,0,179,.25)]">
    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/80">{{ $label }}</p>
    <p class="mt-[6px] text-[28px] font-bold leading-none text-white">{{ $value }}</p>
    @if ($hint)
        <p class="mt-[6px] text-[11px] text-white/70">{{ $hint }}</p>
    @endif
</article>
