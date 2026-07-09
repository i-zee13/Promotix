@props(['align' => 'right'])

<div
    class="figma-sa-dash-dropdown inline-block"
    x-data="{
        open: false,
        dropdownId: null,
        align: @js($align),
        menuStyle: '',
        toggle() {
            const next = !this.open;
            if (next) {
                this.$dispatch('figma-sa-dropdown-open', { id: this.dropdownId });
            }
            this.open = next;
        },
        closeIfOutside(event) {
            if (!this.open) {
                return;
            }
            if (this.$refs.trigger?.contains(event.target)) {
                return;
            }
            if (this.$refs.menu?.contains(event.target)) {
                return;
            }
            this.open = false;
        },
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
    x-init="dropdownId = 'fd-' + Math.random().toString(36).slice(2)"
    x-effect="if (open) { $nextTick(() => positionMenu()); }"
    @figma-sa-dropdown-open.window="if ($event.detail.id !== dropdownId) open = false"
    @keydown.escape.window="open = false"
    @click.window="closeIfOutside($event)"
>
    <div class="figma-sa-dash-dropdown-trigger" x-ref="trigger" @click.capture.stop="toggle()">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div
            x-ref="menu"
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
