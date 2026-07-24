<div class="rounded-[10px] border border-white/15 bg-[color-mix(in_srgb,var(--brand-primary,#6400B2)_18%,#101010)] p-[18px]">
    <h2 class="text-[16px] font-semibold text-white">Login history</h2>
    <p class="mt-[4px] text-[12px] text-white/60">Recent sign-ins for this account.</p>

    <div class="mt-[14px] overflow-x-auto rounded-[8px] border border-white/10">
        <table class="min-w-full text-left text-[12px]">
            <thead class="bg-black/30 text-white/70">
                <tr>
                    <th class="px-[12px] py-[8px] font-semibold">When</th>
                    <th class="px-[12px] py-[8px] font-semibold">IP</th>
                    <th class="px-[12px] py-[8px] font-semibold">Device</th>
                    <th class="px-[12px] py-[8px] font-semibold">Browser</th>
                    <th class="px-[12px] py-[8px] font-semibold">Event</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @forelse ($loginHistories ?? [] as $entry)
                    <tr class="text-white/85">
                        <td class="px-[12px] py-[9px] whitespace-nowrap">{{ $entry->created_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                        <td class="px-[12px] py-[9px] font-mono text-[11px]">{{ $entry->ip_address ?? '—' }}</td>
                        <td class="px-[12px] py-[9px]">{{ $entry->device ?? '—' }}</td>
                        <td class="px-[12px] py-[9px]">{{ $entry->browser ?? '—' }}</td>
                        <td class="px-[12px] py-[9px]">
                            <span class="rounded-full px-[8px] py-[2px] text-[10px] font-semibold text-white" style="background:var(--brand-primary,#6400B2);">
                                {{ ucfirst($entry->event ?? $entry->status ?? 'login') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-[12px] py-[18px] text-center text-white/50">No logins recorded yet. Sign out and sign back in to create the first entry.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
