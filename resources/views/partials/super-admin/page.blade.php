@props(['title' => null, 'subtitle' => null])

<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]">
    <section class="figma-sa-page mx-auto w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[24px] xl:pt-[56px]">
        @if ($title)
            <div class="mb-[20px]">
                <h1 class="text-[28px] font-semibold text-[#a9a9a9] sm:text-[32px]">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-[6px] text-[13px] text-[#8c8787]">{{ $subtitle }}</p>
                @endif
            </div>
        @endif
        @include('partials.super-admin.flash')
        {{ $slot }}
    </section>
</div>
