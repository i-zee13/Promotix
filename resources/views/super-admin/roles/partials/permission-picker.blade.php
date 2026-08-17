@php
    /** @var array<string, \Illuminate\Support\Collection<int, \App\Models\Permission>> $groupedPermissions */
    $checkedIds = $checkedIds ?? [];
@endphp

<div x-data="{ filter: '' }" class="figma-sa-permissions">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <label class="figma-sa-label">Permissions</label>
        <input type="search" x-model="filter" placeholder="Filter permissions..." class="figma-input w-full max-w-xs">
    </div>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 figma-sa-perm-grid">
        @foreach ($groupedPermissions as $groupLabel => $items)
            <div class="figma-sa-row figma-sa-perm-group p-3" x-data="{ toggleAll(state) { this.$root.querySelectorAll('input[type=checkbox][data-perm]').forEach(el => el.checked = state); } }">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="figma-sa-perm-group__title">{{ $groupLabel }}</p>
                    <div class="flex gap-2 text-[10px] uppercase">
                        <button type="button" class="figma-sa-perm-group__action" @click="toggleAll(true)">All</button>
                        <button type="button" class="figma-sa-perm-group__action figma-sa-perm-group__action--danger" @click="toggleAll(false)">None</button>
                    </div>
                </div>
                <div class="space-y-1">
                    @foreach ($items as $p)
                        @php
                            $displayName = \App\Support\PermissionCatalog::displayName($p->slug, $p->name);
                            $description = \App\Support\PermissionDescription::for($p->slug, $p->route_name);
                            $filterHaystack = strtolower($displayName.' '.$p->slug.' '.$description);
                        @endphp
                        <label class="figma-sa-perm-item flex cursor-pointer items-start gap-2 rounded px-2 py-1.5"
                            x-show="filter === '' || '{{ addslashes($filterHaystack) }}'.includes(filter.toLowerCase())">
                            <input type="checkbox" data-perm name="permissions[]" value="{{ $p->id }}" @checked(in_array($p->id, $checkedIds)) class="figma-sa-checkbox mt-0.5 shrink-0 rounded">
                            <span class="min-w-0 flex-1">
                                <span class="figma-sa-perm-item__name block">{{ $displayName }}</span>
                                <span class="figma-sa-perm-item__desc mt-0.5 block text-[10px] leading-snug">{{ $description }}</span>
                                <span class="figma-sa-perm-item__slug mt-0.5 block font-mono text-[10px]">{{ $p->slug }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
