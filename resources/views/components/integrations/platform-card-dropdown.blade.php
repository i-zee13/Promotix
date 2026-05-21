@props(['label' => 'Platform options', 'align' => 'right'])

<x-super-admin.dashboard-dropdown :align="$align">
    <x-slot:trigger>
        <button type="button" @click.stop="open = !open" class="figma-platform-kebab" aria-label="{{ $label }}">
            <span class="flex flex-col items-center gap-[4px]" aria-hidden="true">
                <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
            </span>
        </button>
    </x-slot:trigger>
    {{ $slot }}
</x-super-admin.dashboard-dropdown>
