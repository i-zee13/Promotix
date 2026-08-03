<div class="rounded-[10px] border border-[#b487ff]/35 bg-[linear-gradient(180deg,#6400b2_0%,#42007a_100%)] p-[16px]">
    <h2 class="text-[16px] font-semibold text-white">Security activity</h2>
    <p class="mt-[3px] text-[11px] text-white/75">Recent sign-ins for this account.</p>

    <div class="mt-[10px] max-h-[360px] overflow-auto rounded-[8px] border border-white/20 bg-black/15">
        <table class="min-w-full text-left text-[11px]">
            <thead class="bg-white/10 text-white/90">
                <tr>
                    <th class="px-[10px] py-[7px] font-semibold">When</th>
                    <th class="px-[10px] py-[7px] font-semibold">IP</th>
                    <th class="px-[10px] py-[7px] font-semibold">Device</th>
                    <th class="px-[10px] py-[7px] font-semibold">Browser</th>
                    <th class="px-[10px] py-[7px] font-semibold">Event</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/15">
                @forelse (collect($loginHistories ?? [])->take(16) as $entry)
                    <tr class="text-white/95">
                        <td class="whitespace-nowrap px-[10px] py-[7px]">{{ $entry->created_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                        <td class="px-[10px] py-[7px] font-mono text-[10px]">{{ $entry->ip_address ?? '—' }}</td>
                        <td class="px-[10px] py-[7px]">{{ $entry->device ?? '—' }}</td>
                        <td class="px-[10px] py-[7px]">{{ $entry->browser ?? '—' }}</td>
                        <td class="px-[10px] py-[7px]">
                            <span class="rounded-full bg-white px-[7px] py-[2px] text-[9px] font-semibold text-[#5a1297]">
                                {{ ucfirst($entry->event ?? $entry->status ?? 'login') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-[10px] py-[14px] text-center text-white/70">No login history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
