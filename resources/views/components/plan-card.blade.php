@props([
    'name',
    'price',
    'forText',
    'features' => [],
    'buttonText' => 'Active',
    'buttonStyle' => 'secondary', // 'secondary' | 'primary'
    'class' => '',
])

<article
    {{ $attributes->merge(['class' => 'flex flex-col rounded-xl bg-gradient-to-br from-accent to-accent-hover p-0 shadow-lg ' . $class]) }}
>
    {{-- Header strip --}}
    <div class="flex items-start justify-between gap-2 rounded-t-xl bg-gray-500/30 px-4 py-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-white/90">{{ $name }}</p>
            <p class="mt-0.5 text-sm font-semibold text-white">{{ $price }}</p>
        </div>
        <button type="button" class="shrink-0 rounded p-1.5 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Options for {{ $name }}">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
        </button>
    </div>

    <div class="flex flex-1 flex-col p-6">
        <p class="mb-4 text-sm text-white/90">{{ $forText }}</p>
        <ul class="mb-6 flex-1 space-y-2 text-sm text-white" role="list">
            @foreach ($features as $feature)
                <li class="flex gap-2">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-white/80" aria-hidden="true"></span>
                    <span>{{ $feature }}</span>
                </li>
            @endforeach
        </ul>
        <a
            href="#"
            class="block w-full rounded-xl py-2.5 text-center text-sm font-medium text-white transition {{ $buttonStyle === 'primary' ? 'bg-accent hover:bg-accent-hover' : 'bg-gray-900 hover:bg-gray-800' }}"
        >
            {{ $buttonText }}
        </a>
    </div>
</article>
