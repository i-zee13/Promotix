@props(['align' => 'right'])

<div
    class="figma-sa-dash-dropdown inline-block"
    x-data="{
        open: false,
        align: @js($align),
        menuStyle: '',
        positionMenu() {
            const btn = this.$refs.trigger?.querySelector('button, [type=button], summary');
            if (! btn) return;
            const r = btn.getBoundingClientRect();
            const top = Math.round(r.bottom + 6);
            if (this.align === 'right') {
                const left = Math.round(r.right);
                this.menuStyle = `top:${top}px;left:${left}px;transform:translateX(-100%);`;
            } else {
                this.menuStyle = `top:${top}px;left:${Math.round(r.left)}px;`;
            }
        },
    }"
    x-effect="if (open) { $nextTick(() => positionMenu()); }"
    @keydown.escape.window="open = false"
    @click.outside="if (!$refs.trigger.contains($event.target)) open = false"
>
    <div class="figma-sa-dash-dropdown-trigger" x-ref="trigger" @click.stop>
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            :style="menuStyle"
            class="figma-sa-dash-dropdown-menu figma-sa-dash-dropdown-menu--portal"
            :class="{ 'figma-sa-dash-dropdown-menu--align-left': align === 'left' }"
            @scroll.window.passive="if (open) positionMenu()"
            @resize.window="if (open) positionMenu()"
        >
            <div class="py-1" @click="open = false">
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
