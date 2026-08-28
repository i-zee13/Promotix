@props([
    'rows' => [],
    'scrollable' => false,
    'showViewAll' => false,
])

<div @class(['figma-sa-subs-panel', 'figma-sa-cross-domain-panel' => $scrollable])>
    <div class="figma-sa-cross-domain-head">
        <div>
            <h2 class="figma-sa-cross-domain-title">Cross-domain intelligence</h2>
            <p class="figma-sa-cross-domain-subtitle">Evidence scores only — never auto-block from this panel.</p>
        </div>
        @if ($showViewAll)
            <a href="{{ route('super-admin.traffic.cross-domain') }}" class="figma-sa-cross-domain-view-all">View all</a>
        @endif
    </div>

    <div @class(['figma-sa-subs-table-scroll', 'figma-sa-cross-domain-scroll' => $scrollable])>
        <table class="figma-sa-subs-table figma-sa-cross-domain-table">
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Domains</th>
                    <th>Domain similarity</th>
                    <th>Hits</th>
                    <th>Evidence</th>
                    <th>Bot max</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="figma-sa-subs-row">
                        <td><span class="figma-sa-subs-plan-tier">{{ $row['ip'] }}</span></td>
                        <td>
                            <span class="figma-sa-subs-billing">{{ $row['domain_count'] }} domains</span>
                            <span class="figma-sa-subs-date">{{ implode(', ', $row['domains']) }}</span>
                        </td>
                        <td>
                            @if (($row['domain_similarity_label'] ?? '—') === '—')
                                <span class="figma-sa-subs-plan-detail">—</span>
                            @else
                                <span class="figma-sa-cross-domain-sim figma-sa-cross-domain-sim--{{ strtolower($row['domain_similarity_label']) }}">
                                    {{ $row['domain_similarity'] }}% · {{ $row['domain_similarity_label'] }}
                                </span>
                                @if (! empty($row['domain_similarity_pair']))
                                    <span class="figma-sa-subs-date">{{ $row['domain_similarity_pair'][0] }} ↔ {{ $row['domain_similarity_pair'][1] }}</span>
                                @endif
                            @endif
                        </td>
                        <td>{{ number_format($row['hits']) }}</td>
                        <td>
                            <span class="figma-sa-subs-plan-tier">{{ $row['evidence_score'] }}</span>
                            <span class="figma-sa-subs-plan-detail">no auto-block</span>
                        </td>
                        <td>{{ $row['max_bot_score'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="figma-sa-subs-empty">No multi-domain IPs in the last 30 days.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
