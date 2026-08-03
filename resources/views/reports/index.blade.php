@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<div class="brand-page-bg min-h-[calc(100vh-49px)]">
    <section class="w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[24px] xl:pt-[56px]">
        <h1 class="mb-[6px] text-[28px] font-semibold text-[#a9a9a9] sm:text-[36px]">Reports</h1>
        <p class="mb-[24px] max-w-[640px] text-[13px] text-[#8c8787]">
            Export click measurement and invalid traffic datasets for audits, Google reviews, and offline analysis.
        </p>

        <div class="grid gap-[14px] md:grid-cols-2 xl:grid-cols-2">
            @foreach ($exports as $export)
                <article class="rounded-[10px] border border-white/15 bg-[#151515] p-[18px]">
                    <h2 class="text-[16px] font-semibold text-white">{{ $export['title'] }}</h2>
                    <p class="mt-[6px] text-[12px] text-[#a9a9a9]">{{ $export['description'] }}</p>
                    <div class="mt-[14px] flex flex-wrap items-center gap-[10px]">
                        <a href="{{ $export['href'] }}" class="rounded-[6px] bg-[#6400B2] px-[14px] py-[8px] text-[12px] font-semibold text-white hover:bg-[#7a1acc]">
                            {{ $export['label'] }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-[18px] rounded-[10px] border border-white/10 bg-black/20 p-[16px] text-[12px] text-white/65">
            Tip: open <a class="text-[#B893D8] hover:text-white" href="{{ route('paid-marketing.detailed') }}">Advanced View</a>
            or the <a class="text-[#B893D8] hover:text-white" href="{{ route('paid-marketing.dashboard') }}">Paid Dashboard</a>
            first if you need filtered exports for a specific domain, campaign, or date range.
        </div>
    </section>
</div>
@endsection
