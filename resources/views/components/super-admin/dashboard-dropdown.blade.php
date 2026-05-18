@props(['align' => 'right'])

<div class="figma-sa-dash-dropdown relative inline-block" x-data="{ open: false }" @click.outside="open = false">
    <div @click.stop>
        {{ $trigger }}
    </div>
    <div
        x-show="open"
        x-cloak
        @class([
            'figma-sa-dash-dropdown-menu',
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
        ])
    >
        <div class="py-1" @click="open = false">
            {{ $slot }}
        </div>
    </div>
</div>
