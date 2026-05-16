@props([
    'selectAttributes' => '',
    'inputAttributes' => '',
    'buttonAttributes' => '',
    'showButton' => true,
])

<div {{ $attributes->merge(['class' => 'figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black']) }}>
    <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
        <span class="figma-filter-label mb-[3px] text-[8px] font-semibold text-black/70">Campaigns</span>
        <select {!! $selectAttributes !!} class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:border-[#9a1aff] focus:ring-1 focus:ring-[#9a1aff]/30">
            <option>All campaigns</option>
        </select>
    </label>
    <label class="flex w-[178px] flex-col justify-center px-[12px]">
        <span class="figma-filter-label mb-[3px] text-[8px] font-semibold text-black/70">Filter by path</span>
        <input {!! $inputAttributes !!} placeholder="Filter by path" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:border-[#9a1aff] focus:ring-1 focus:ring-[#9a1aff]/30">
    </label>
    @if ($showButton)
        <button type="button" {!! $buttonAttributes !!} class="figma-filter-action flex w-[34px] shrink-0 items-center justify-center bg-[#6400B2] text-white" aria-label="Filters">
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8M8 12h8M8 17h8"/></svg>
        </button>
    @endif
</div>
