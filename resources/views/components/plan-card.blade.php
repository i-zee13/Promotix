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
    {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-xl border border-white/20 bg-[#6400B2] p-0 shadow-[0_0_18px_rgba(100,0,179,.35)] ' . $class]) }}
>
    <div class="border-b border-white/15 px-4 py-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-white">{{ $name }}</p>
        <p class="mt-0.5 text-lg font-bold text-white">{{ $price }}</p>
    </div>

    <div class="flex flex-1 flex-col p-6">
        <p class="mb-4 text-sm text-white/90">{{ $forText }}</p>
        <ul class="mb-6 flex-1 space-y-2 text-sm text-white" role="list">
            @foreach ($features as $feature)
                <li class="flex gap-2">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-white" aria-hidden="true"></span>
                    <span>{{ $feature }}</span>
                </li>
            @endforeach
        </ul>
        <a
            href="#"
            class="block w-full rounded-xl py-2.5 text-center text-sm font-semibold transition {{ $buttonStyle === 'primary' ? 'bg-white text-[#6400B2] hover:bg-white/90' : 'border border-white/40 bg-transparent text-white hover:bg-white/10' }}"
        >
            {{ $buttonText }}
        </a>
    </div>
</article>
