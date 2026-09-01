<div class="overflow-hidden rounded-[10px] border border-[var(--brand-primary,#FF6600)]">
    <div class="bg-[var(--brand-primary,#FF6600)] px-[16px] py-[14px]">
        <h2 class="text-[16px] font-semibold text-white">Security activity</h2>
        <p class="mt-[3px] text-[11px] text-white/85">Recent sign-ins for this account.</p>
    </div>

    <div class="max-h-[360px] overflow-auto bg-white">
        <table class="min-w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-[#fff4ee] text-[#121212]">
                <tr>
                    <th class="px-[10px] py-[7px] font-semibold">When</th>
                    <th class="px-[10px] py-[7px] font-semibold">IP</th>
                    <th class="px-[10px] py-[7px] font-semibold">Device</th>
                    <th class="px-[10px] py-[7px] font-semibold">Browser</th>
                    <th class="px-[10px] py-[7px] font-semibold">Event</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/8">
                @forelse (collect($loginHistories ?? [])->take(16) as $entry)
                    <tr class="bg-white text-[#121212]">
                        <td class="whitespace-nowrap px-[10px] py-[7px]">{{ $entry->created_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                        <td class="px-[10px] py-[7px] font-mono text-[10px]">{{ $entry->ip_address ?? '—' }}</td>
                        <td class="px-[10px] py-[7px]">{{ $entry->device ?? '—' }}</td>
                        <td class="px-[10px] py-[7px]">{{ $entry->browser ?? '—' }}</td>
                        <td class="px-[10px] py-[7px]">
                            <span class="rounded-full bg-[var(--brand-primary,#FF6600)] px-[7px] py-[2px] text-[9px] font-semibold text-white">
                                {{ ucfirst($entry->event ?? $entry->status ?? 'login') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-[10px] py-[14px] text-center text-[#666]">No login history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
