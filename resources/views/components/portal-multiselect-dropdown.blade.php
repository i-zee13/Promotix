@props([
    'name',
    'options' => [],
    'selected' => [],
    'placeholder' => 'Select options…',
    'searchable' => true,
])

@php
    $normalized = collect($options)->map(function ($opt) {
        $value = (string) ($opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? '');

        return [
            'value' => $value,
            'label' => (string) ($opt['label'] ?? $opt['hostname'] ?? $value),
        ];
    })->filter(fn ($opt) => $opt['value'] !== '')->values()->all();

    $selectedValues = collect($selected)->map(fn ($v) => (string) $v)->values()->all();
@endphp

<div
    {{ $attributes->merge(['class' => 'figma-ms-dropdown']) }}
    x-data="{
        open: false,
        query: '',
        selected: @js($selectedValues),
        options: @js($normalized),
        placeholder: @js($placeholder),
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        isSelected(value) {
            return this.selected.includes(String(value));
        },
        toggleItem(value) {
            value = String(value);
            if (this.isSelected(value)) {
                this.selected = this.selected.filter((v) => v !== value);
            } else {
                this.selected.push(value);
            }
        },
        filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.options;
            return this.options.filter((o) => o.label.toLowerCase().includes(q));
        },
        triggerLabel() {
            if (!this.selected.length) return this.placeholder;
            if (this.selected.length === 1) {
                const match = this.options.find((o) => o.value === this.selected[0]);
                return match ? match.label : '1 selected';
            }
            return `${this.selected.length} selected`;
        },
    }"
    @click.outside="open = false"
>
    <template x-for="val in selected" :key="'{{ $name }}-' + val">
        <input type="hidden" name="{{ $name }}" :value="val">
    </template>

    <button type="button" class="figma-ms-dropdown__trigger" @click="toggle()" :aria-expanded="open">
        <span class="truncate" x-text="triggerLabel()"></span>
        <svg class="figma-ms-dropdown__chevron" :class="open && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition class="figma-ms-dropdown__menu">
        @if ($searchable)
            <input
                type="search"
                x-ref="search"
                x-model="query"
                placeholder="Search…"
                class="figma-ms-dropdown__search"
                @click.stop
                @keydown.escape.stop="open = false"
            >
        @endif
        <div class="figma-ms-dropdown__options promotix-slim-scroll">
            <template x-for="opt in filtered()" :key="opt.value">
                <label class="figma-ms-dropdown__check" @click.stop>
                    <input type="checkbox" :checked="isSelected(opt.value)" @change="toggleItem(opt.value)">
                    <span x-text="opt.label"></span>
                </label>
            </template>
            <p x-show="filtered().length === 0" class="figma-ms-dropdown__empty">No matches</p>
        </div>
    </div>
</div>
