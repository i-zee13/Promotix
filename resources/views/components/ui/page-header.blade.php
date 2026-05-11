@props([
    'title' => '',
    'subtitle' => null,
    'breadcrumbs' => [], // [['label' => 'Foo', 'href' => '/foo'], ['label' => 'Bar']]
])

<header {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if (! empty($breadcrumbs))
            <nav class="mb-2 flex items-center gap-1 text-xs text-night-400" aria-label="Breadcrumb">
                @foreach ($breadcrumbs as $i => $crumb)
                    @if (! empty($crumb['href']) && ! $loop->last)
                        <a href="{{ $crumb['href'] }}" class="hover:text-white">{{ $crumb['label'] }}</a>
                    @else
                        <span class="text-night-200">{{ $crumb['label'] }}</span>
                    @endif
                    @unless ($loop->last)
                        <span aria-hidden="true">/</span>
                    @endunless
                @endforeach
            </nav>
        @endif

        <h1 class="brand-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="brand-page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
