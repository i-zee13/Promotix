@props(['menuId', 'label' => 'Platform options', 'align' => 'right'])

<div
    class="figma-sa-dash-dropdown inline-block"
    x-data="platformCardDropdown(@js($menuId), @js($align))"
    x-init="init()"
    @keydown.escape.window="close()"
>
    <div class="figma-sa-dash-dropdown-trigger" x-ref="trigger">
        <button type="button" @click.stop="toggle()" class="figma-platform-kebab" aria-label="{{ $label }}" :aria-expanded="isOpen">
            <span class="flex flex-col items-center gap-[4px]" aria-hidden="true">
                <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
                <span class="block h-[4px] w-[4px] rounded-full bg-current"></span>
            </span>
        </button>
    </div>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="isOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            :style="menuStyle"
            class="figma-sa-dash-dropdown-menu figma-sa-dash-dropdown-menu--portal figma-platform-dropdown-panel"
            :class="{ 'figma-sa-dash-dropdown-menu--align-left': align === 'left' }"
            @scroll.window.passive="if (isOpen) positionMenu()"
            @resize.window="if (isOpen) positionMenu()"
        >
            <div class="py-1" @click="onMenuClick($event)">
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
