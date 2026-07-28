@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'figma-sa-card']) }}>
    @if ($title)
        <h2 class="figma-sa-card__title">{{ $title }}</h2>
    @endif
    @if ($subtitle)
        <p class="figma-sa-card__subtitle">{{ $subtitle }}</p>
    @endif
    <div class="{{ $title || $subtitle ? 'figma-sa-card__body' : '' }}">
        {{ $slot }}
    </div>
</div>
