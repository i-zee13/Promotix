@props([
    'column',
    'label',
    'sort' => null,
    'dir' => 'asc',
    'url' => null,
])

@php
    $sort = $sort ?? request('sort');
    $dir = strtolower((string) ($dir ?? request('dir', 'asc'))) === 'desc' ? 'desc' : 'asc';
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    $href = $url;
    if ($href === null) {
        $href = request()->fullUrlWithQuery([
            'sort' => $column,
            'dir' => $nextDir,
        ]);
    }
    $state = $sort === $column
        ? ($dir === 'desc' ? 'is-sortable is-desc' : 'is-sortable is-asc')
        : 'is-sortable';
@endphp

<th {{ $attributes->merge(['class' => 'promotix-th']) }}>
    <a href="{{ $href }}" class="promotix-sortable {{ $state }}">
        <span>{{ $label }}</span>
        <span class="promotix-sortable-arrows" aria-hidden="true">
            <span class="promotix-sortable-up">▲</span>
            <span class="promotix-sortable-down">▼</span>
        </span>
    </a>
</th>
