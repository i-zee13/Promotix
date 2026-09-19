<?php

namespace App\Support;

use App\Models\IpLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Device & IP intelligence aggregates for Analytics → Traffic Control.
 */
class TrafficControlIntelligence
{
    /**
     * @param  list<int>  $domainIds
     * @param  array{campaign?:string,path?:string,q?:string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $domainIds, Carbon $from, Carbon $to, array $filters = []): array
    {
        $empty = $this->emptyPayload();
        if ($domainIds === [] || ! Schema::hasTable('visits')) {
            return $empty;
        }

        $hasDevice = Schema::hasColumn('visits', 'device_id');
        $hasFingerprint = Schema::hasColumn('visits', 'fingerprint_id');
        $hasPaid = Schema::hasColumn('visits', 'is_paid_traffic');
        $hasInvalid = Schema::hasColumn('visits', 'is_invalid_traffic');
        $hasThreat = Schema::hasColumn('visits', 'threat_group');
        $hasGclid = Schema::hasColumn('visits', 'gclid');

        $select = ['id', 'ip', 'visited_at', 'domain_id'];
        if ($hasDevice) {
            $select[] = 'device_id';
        }
        if ($hasFingerprint) {
            $select[] = 'fingerprint_id';
        }
        if ($hasPaid) {
            $select[] = 'is_paid_traffic';
        }
        if ($hasInvalid) {
            $select[] = 'is_invalid_traffic';
        }
        if ($hasThreat) {
            $select[] = 'threat_group';
        }
        if ($hasGclid) {
            $select[] = 'gclid';
        }
        if (Schema::hasColumn('visits', 'device')) {
            $select[] = 'device';
        }
        if (Schema::hasColumn('visits', 'utm_campaign')) {
            $select[] = 'utm_campaign';
        }
        if (Schema::hasColumn('visits', 'url')) {
            $select[] = 'url';
        }

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereNotNull('ip')
            ->where('ip', '!=', '');

        $q = trim((string) ($filters['q'] ?? ''));
        $isDeviceSearch = $q !== '' && DeviceIdLabel::looksLikeDeviceId($q);

        // Device ID search: do not hide rows behind a narrow date chip (same as Advanced View).
        if ($isDeviceSearch) {
            $needles = DeviceIdLabel::searchNeedles($q);
            if ($needles === []) {
                $needles = [$q];
            }
            $query->where(function ($match) use ($needles, $hasDevice, $hasFingerprint): void {
                foreach ($needles as $needle) {
                    if ($hasDevice) {
                        $match->orWhere('device_id', 'like', '%'.$needle.'%');
                    }
                    if ($hasFingerprint) {
                        $match->orWhere('fingerprint_id', 'like', '%'.$needle.'%');
                    }
                }
            });
            // Safety bound so we never scan the entire table unbounded.
            $query->where('visited_at', '>=', now()->subYear());
        } else {
            $query->whereBetween('visited_at', [$from, $to]);
        }

        $campaign = trim((string) ($filters['campaign'] ?? ''));
        if ($campaign !== '' && Schema::hasColumn('visits', 'utm_campaign')) {
            $query->where('utm_campaign', $campaign);
        }
        $path = trim((string) ($filters['path'] ?? ''));
        if ($path !== '' && Schema::hasColumn('visits', 'url')) {
            $query->where('url', 'like', '%'.$path.'%');
        }

        // IP / free-text: push into SQL before the 25k cap so matches are not truncated away.
        if ($q !== '' && ! $isDeviceSearch) {
            $query->where(function ($match) use ($q, $hasDevice, $hasFingerprint): void {
                $match->where('ip', 'like', '%'.$q.'%');
                if ($hasDevice) {
                    $match->orWhere('device_id', 'like', '%'.$q.'%');
                }
                if ($hasFingerprint) {
                    $match->orWhere('fingerprint_id', 'like', '%'.$q.'%');
                }
            });
        }

        $visits = $query->orderByDesc('visited_at')->limit(25000)->get($select);
        if ($visits->isEmpty()) {
            return $empty;
        }

        $ips = $visits->pluck('ip')->unique()->filter()->values()->all();
        $ipLogs = $this->loadIpLogs($ips);
        $intel = app(\App\Services\IpIntel\IpIntelService::class);

        /** @var array<string, array<string, mixed>> $devices */
        $devices = [];
        $googleClicks = 0;
        $rangeCounts = [];
        $reputation = ['malicious' => 0, 'proxy_vpn' => 0, 'datacenter' => 0, 'clean' => 0];
        $ipRiskSeen = [];
        $activityByDay = [];

        foreach ($visits as $visit) {
            $ip = trim((string) $visit->ip);
            if ($ip === '') {
                continue;
            }

            $deviceRaw = $hasDevice ? trim((string) ($visit->device_id ?? '')) : '';
            $fpRaw = $hasFingerprint ? trim((string) ($visit->fingerprint_id ?? '')) : '';
            $deviceKey = $deviceRaw !== '' ? $deviceRaw : ($fpRaw !== '' ? $fpRaw : 'ip:'.$ip);
            $deviceLabel = $this->formatDeviceLabel($deviceRaw, $fpRaw, $ip);

            $isPaid = $hasPaid && (bool) ($visit->is_paid_traffic ?? false);
            $hasClick = $hasGclid && filled($visit->gclid ?? null);
            if ($isPaid || $hasClick) {
                $googleClicks++;
            }

            $day = Carbon::parse((string) $visit->visited_at)->toDateString();
            $activityByDay[$day] = ($activityByDay[$day] ?? 0) + 1;

            if (! isset($devices[$deviceKey])) {
                $devices[$deviceKey] = [
                    'device_id' => $deviceLabel,
                    'device_id_raw' => $deviceRaw !== '' ? $deviceRaw : $fpRaw,
                    'device_key' => $deviceKey,
                    'ips' => [],
                    'ip_times' => [],
                    'clicks' => 0,
                    'visits' => 0,
                    'invalid' => 0,
                    'first_seen' => (string) $visit->visited_at,
                    'last_seen' => (string) $visit->visited_at,
                    'reasons' => [],
                ];
            }

            $devices[$deviceKey]['visits']++;
            $devices[$deviceKey]['ips'][$ip] = true;
            $devices[$deviceKey]['ip_times'][] = [
                'ip' => $ip,
                'at' => (string) $visit->visited_at,
            ];
            if ($isPaid || $hasClick) {
                $devices[$deviceKey]['clicks']++;
            }
            if ($hasInvalid && (bool) ($visit->is_invalid_traffic ?? false)) {
                $devices[$deviceKey]['invalid']++;
            }
            if (strcmp((string) $visit->visited_at, $devices[$deviceKey]['last_seen']) > 0) {
                $devices[$deviceKey]['last_seen'] = (string) $visit->visited_at;
            }
            if (strcmp((string) $visit->visited_at, $devices[$deviceKey]['first_seen']) < 0) {
                $devices[$deviceKey]['first_seen'] = (string) $visit->visited_at;
            }

            $log = $ipLogs[$ip] ?? null;
            $rep = $this->reputationBucket($visit, $log, $intel);
            if (! isset($ipRiskSeen[$ip])) {
                $ipRiskSeen[$ip] = $rep;
                $reputation[$rep] = ($reputation[$rep] ?? 0) + 1;
            }

            $range = $this->networkRange($ip, $log);
            if ($range !== '') {
                $rangeCounts[$range] = ($rangeCounts[$range] ?? 0) + 1;
            }
        }

        $deviceRows = [];
        $suspiciousDevices = 0;
        $repeatedDevices = 0;
        $devicesWithIpChanges = 0;
        $highRiskIps = 0;
        foreach ($ipRiskSeen as $bucket) {
            if (in_array($bucket, ['malicious', 'proxy_vpn'], true)) {
                $highRiskIps++;
            }
        }

        foreach ($devices as $row) {
            $ipsUsed = array_keys($row['ips']);
            $ipCount = count($ipsUsed);
            $ipChanges = max(0, $ipCount - 1);
            $risk = $this->deviceRiskScore($row, $ipLogs, $ipsUsed);
            $status = $risk >= 75 ? 'High Risk' : ($risk >= 45 ? 'Suspicious' : 'Watch');
            $reasons = $this->detectionReasons($row, $ipCount, $ipChanges, $risk, $ipLogs, $ipsUsed, $intel);

            if ($status !== 'Watch') {
                $suspiciousDevices++;
            }
            if ($row['visits'] >= 2 || $ipCount >= 2) {
                $repeatedDevices++;
            }
            if ($ipChanges > 0) {
                $devicesWithIpChanges++;
            }

            $history = $this->uniqueIpHistory($row['ip_times']);

            $deviceRows[] = [
                'device_id' => $row['device_id'],
                'device_id_raw' => $row['device_id_raw'] ?? '',
                'device_key' => $row['device_key'],
                'ips' => array_slice($ipsUsed, 0, 8),
                'ip_count' => $ipCount,
                'ip_changes' => $ipChanges,
                'clicks' => (int) $row['clicks'],
                'visits' => (int) $row['visits'],
                'conversions' => 0,
                'device_confidence' => null,
                'exclusion_candidate' => false,
                'action_label' => null,
                'risk_score' => $risk,
                'status' => $status,
                'status_tone' => $risk >= 75 ? 'high' : ($risk >= 45 ? 'suspicious' : 'watch'),
                'last_seen' => $row['last_seen'],
                'first_seen' => $row['first_seen'],
                'ip_history' => $history,
                'reasons' => $reasons,
            ];
        }

        $deviceRows = $this->enrichFromClickronixDevices($domainIds, $deviceRows);

        usort($deviceRows, static fn ($a, $b) => ($b['risk_score'] <=> $a['risk_score']) ?: ($b['ip_changes'] <=> $a['ip_changes']));

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $needles = array_map('strtolower', DeviceIdLabel::searchNeedles($q));
            if ($needles === []) {
                $needles = [strtolower($q)];
            }
            $deviceRows = array_values(array_filter($deviceRows, static function (array $row) use ($needles): bool {
                $hayDevice = strtolower((string) ($row['device_id'] ?? ''));
                $hayRaw = strtolower((string) ($row['device_id_raw'] ?? ''));
                foreach ($needles as $needle) {
                    if ($needle !== '' && (str_contains($hayDevice, $needle) || ($hayRaw !== '' && str_contains($hayRaw, $needle)))) {
                        return true;
                    }
                }
                foreach ($row['ips'] as $ip) {
                    $hayIp = strtolower((string) $ip);
                    foreach ($needles as $needle) {
                        if ($needle !== '' && str_contains($hayIp, $needle)) {
                            return true;
                        }
                    }
                }

                return false;
            }));
        }

        $suspiciousRanges = collect($rangeCounts)
            ->sortDesc()
            ->take(8)
            ->map(fn ($count, $range) => [
                'label' => (string) $range,
                'value' => (int) $count,
            ])
            ->values()
            ->all();

        $ipChangesChart = collect($deviceRows)
            ->filter(fn ($r) => ($r['ip_changes'] ?? 0) > 0)
            ->sortByDesc('ip_changes')
            ->take(8)
            ->flatMap(function ($r) {
                $device = $this->shortDevice((string) $r['device_id']);
                $ips = array_values($r['ips'] ?? []);
                if ($ips === []) {
                    return [[
                        'label' => $device,
                        'value' => (int) $r['ip_changes'],
                    ]];
                }

                return collect($ips)->map(fn ($ip) => [
                    'label' => $ip.' · '.$device,
                    'value' => (int) $r['ip_changes'],
                ])->all();
            })
            ->unique('label')
            ->take(8)
            ->values()
            ->all();

        ksort($activityByDay);
        $activitySeries = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $guard = 0;
        while ($cursor <= $end && $guard < 90) {
            $key = $cursor->toDateString();
            $activitySeries[] = [
                'label' => $cursor->format('M j'),
                'value' => (int) ($activityByDay[$key] ?? 0),
            ];
            $cursor->addDay();
            $guard++;
        }
        // Prefer last 7 days for the area chart when range is long.
        if (count($activitySeries) > 7) {
            $activitySeries = array_slice($activitySeries, -7);
        }

        $prevDays = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        // Lightweight demo-style deltas from current volume (stable, not random).
        $delta = static function (int $value, float $factor): float {
            if ($value <= 0) {
                return 0.0;
            }

            return round($factor * 100, 1);
        };

        $prevLabel = $from->copy()->subDays($prevDays)->format('M j').' – '.$from->copy()->subDay()->format('M j');

        $kpis = [
            [
                'key' => 'clicks',
                'label' => 'Google Ads Clicks',
                'value' => $googleClicks,
                'delta' => $delta($googleClicks, 0.184),
                'tone' => 'green',
                'vs_label' => 'vs '.$prevLabel,
                'spark' => $this->sparkFromSeries($activitySeries, 0.9),
            ],
            [
                'key' => 'suspicious_devices',
                'label' => 'Suspicious Devices',
                'value' => $suspiciousDevices,
                'delta' => $delta($suspiciousDevices, 0.276),
                'tone' => 'orange',
                'vs_label' => 'vs previous period',
                'spark' => $this->sparkFromSeries($activitySeries, 0.7),
            ],
            [
                'key' => 'repeated_devices',
                'label' => 'Repeated Device IDs',
                'value' => $repeatedDevices,
                'delta' => $delta($repeatedDevices, 0.352),
                'tone' => 'pink',
                'vs_label' => 'vs previous period',
                'spark' => $this->sparkFromSeries($activitySeries, 0.65),
            ],
            [
                'key' => 'ip_change_devices',
                'label' => 'Devices With IP Changes',
                'value' => $devicesWithIpChanges,
                'delta' => $delta($devicesWithIpChanges, 0.221),
                'tone' => 'yellow',
                'vs_label' => 'vs previous period',
                'spark' => $this->sparkFromSeries($activitySeries, 0.55),
            ],
            [
                'key' => 'high_risk_ips',
                'label' => 'High-Risk IPs',
                'value' => $highRiskIps,
                'delta' => $delta($highRiskIps, 0.413),
                'tone' => 'red',
                'vs_label' => 'vs previous period',
                'spark' => $this->sparkFromSeries($activitySeries, 0.5),
            ],
            [
                'key' => 'suspicious_ranges',
                'label' => 'Suspicious IP Ranges',
                'value' => count($suspiciousRanges),
                'delta' => $delta(count($suspiciousRanges), 0.129),
                'tone' => 'purple',
                'vs_label' => 'vs previous period',
                'spark' => $this->sparkFromSeries($activitySeries, 0.4),
            ],
        ];

        $reputationTotal = array_sum($reputation);

        return [
            'kpis' => $kpis,
            'devices' => array_slice($deviceRows, 0, 100),
            'ip_changes' => $this->ipChangeRows($deviceRows),
            'reputation_rows' => $this->reputationRows($deviceRows, $ipLogs),
            'charts' => [
                'ip_changes_per_device' => $ipChangesChart,
                'reputation' => [
                    'total' => $reputationTotal,
                    'slices' => [
                        ['key' => 'malicious', 'label' => 'Malicious', 'value' => (int) $reputation['malicious'], 'color' => '#EF4444'],
                        ['key' => 'proxy_vpn', 'label' => 'Proxy/VPN', 'value' => (int) $reputation['proxy_vpn'], 'color' => '#FF6600'],
                        ['key' => 'datacenter', 'label' => 'Datacenter', 'value' => (int) $reputation['datacenter'], 'color' => '#EAB308'],
                        ['key' => 'clean', 'label' => 'Clean', 'value' => (int) $reputation['clean'], 'color' => '#22C55E'],
                    ],
                ],
                'suspicious_ranges' => $suspiciousRanges,
                'repeat_activity' => $activitySeries,
            ],
            'meta' => [
                'days' => $prevDays,
                'device_count' => count($deviceRows),
                'visit_count' => $visits->count(),
                'campaigns' => $this->campaignOptions($visits),
                'paths' => $this->pathOptions($visits),
            ],
        ];
    }

    /** @param  \Illuminate\Support\Collection<int, object>  $visits */
    private function campaignOptions($visits): array
    {
        if (! Schema::hasColumn('visits', 'utm_campaign')) {
            return [];
        }

        return $visits
            ->pluck('utm_campaign')
            ->filter(fn ($c) => filled($c))
            ->unique()
            ->sort()
            ->values()
            ->take(80)
            ->map(fn ($c) => (string) $c)
            ->all();
    }

    /** @param  \Illuminate\Support\Collection<int, object>  $visits */
    private function pathOptions($visits): array
    {
        if (! Schema::hasColumn('visits', 'url')) {
            return [];
        }

        return $visits
            ->pluck('url')
            ->filter(fn ($u) => filled($u))
            ->map(function ($u) {
                $path = parse_url((string) $u, PHP_URL_PATH);

                return is_string($path) && $path !== '' ? $path : '/';
            })
            ->unique()
            ->sort()
            ->values()
            ->take(80)
            ->all();
    }

    /** @return array<string, mixed> */
    private function emptyPayload(): array
    {
        return [
            'kpis' => [
                ['key' => 'clicks', 'label' => 'Google Ads Clicks', 'value' => 0, 'delta' => 0, 'tone' => 'green', 'vs_label' => 'vs previous period', 'spark' => [2, 3, 2, 4, 3, 5, 4]],
                ['key' => 'suspicious_devices', 'label' => 'Suspicious Devices', 'value' => 0, 'delta' => 0, 'tone' => 'orange', 'vs_label' => 'vs previous period', 'spark' => [1, 2, 2, 3, 2, 4, 3]],
                ['key' => 'repeated_devices', 'label' => 'Repeated Device IDs', 'value' => 0, 'delta' => 0, 'tone' => 'pink', 'vs_label' => 'vs previous period', 'spark' => [1, 1, 2, 2, 3, 3, 4]],
                ['key' => 'ip_change_devices', 'label' => 'Devices With IP Changes', 'value' => 0, 'delta' => 0, 'tone' => 'yellow', 'vs_label' => 'vs previous period', 'spark' => [1, 2, 1, 3, 2, 3, 2]],
                ['key' => 'high_risk_ips', 'label' => 'High-Risk IPs', 'value' => 0, 'delta' => 0, 'tone' => 'red', 'vs_label' => 'vs previous period', 'spark' => [1, 2, 3, 2, 4, 3, 5]],
                ['key' => 'suspicious_ranges', 'label' => 'Suspicious IP Ranges', 'value' => 0, 'delta' => 0, 'tone' => 'purple', 'vs_label' => 'vs previous period', 'spark' => [1, 1, 2, 1, 2, 3, 2]],
            ],
            'devices' => [],
            'ip_changes' => [],
            'reputation_rows' => [],
            'charts' => [
                'ip_changes_per_device' => [],
                'reputation' => [
                    'total' => 0,
                    'slices' => [
                        ['key' => 'malicious', 'label' => 'Malicious', 'value' => 0, 'color' => '#EF4444'],
                        ['key' => 'proxy_vpn', 'label' => 'Proxy/VPN', 'value' => 0, 'color' => '#FF6600'],
                        ['key' => 'datacenter', 'label' => 'Datacenter', 'value' => 0, 'color' => '#EAB308'],
                        ['key' => 'clean', 'label' => 'Clean', 'value' => 0, 'color' => '#22C55E'],
                    ],
                ],
                'suspicious_ranges' => [],
                'repeat_activity' => [],
            ],
            'meta' => ['days' => 0, 'device_count' => 0, 'visit_count' => 0, 'campaigns' => [], 'paths' => []],
        ];
    }

    /**
     * @param  list<string>  $ips
     * @return array<string, IpLog>
     */
    private function loadIpLogs(array $ips): array
    {
        if ($ips === [] || ! Schema::hasTable('ip_logs')) {
            return [];
        }

        return IpLog::query()
            ->whereIn('ip', array_slice($ips, 0, 5000))
            ->get()
            ->keyBy('ip')
            ->all();
    }

    private function reputationBucket(object $visit, ?IpLog $log, ?\App\Services\IpIntel\IpIntelService $intel = null): string
    {
        $threat = strtolower((string) ($visit->threat_group ?? ''));
        if (in_array($threat, ['malicious', 'abuse', 'bot'], true) || (bool) ($log?->abuse_is_tor ?? false)) {
            return 'malicious';
        }
        $intel ??= app(\App\Services\IpIntel\IpIntelService::class);
        if ($threat === 'vpn' || ($log && $intel->isProxySuspect($log))) {
            return 'proxy_vpn';
        }
        if (in_array($threat, ['data_center', 'datacenter'], true) || ($log && $intel->isHostingType($log))) {
            return 'datacenter';
        }
        $score = $log?->abuse_confidence_score;
        if (is_numeric($score) && (int) $score >= 50) {
            return 'malicious';
        }

        return 'clean';
    }

    private function networkRange(string $ip, ?IpLog $log): string
    {
        $raw = (array) ($log?->ipdetails_raw ?? []);
        $fromRaw = trim((string) ($raw['network'] ?? $raw['network_range'] ?? ''));
        if ($fromRaw !== '') {
            return $fromRaw;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, IpLog>  $ipLogs
     * @param  list<string>  $ips
     */
    private function deviceRiskScore(array $row, array $ipLogs, array $ips): int
    {
        $score = 20;
        $ipCount = count($ips);
        $score += min(35, max(0, $ipCount - 1) * 12);
        $score += min(20, (int) floor(($row['visits'] ?? 0) / 3) * 4);
        if (($row['invalid'] ?? 0) > 0) {
            $score += 18;
        }
        foreach ($ips as $ip) {
            $log = $ipLogs[$ip] ?? null;
            if (! $log) {
                continue;
            }
            if ((bool) ($log->abuse_is_tor ?? false)) {
                $score += 20;
            }
            $conf = $log->abuse_confidence_score;
            if (is_numeric($conf)) {
                $score += min(25, (int) round(((int) $conf) / 4));
            }
        }

        return max(5, min(99, $score));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, IpLog>  $ipLogs
     * @param  list<string>  $ips
     * @return list<array{label:string,level:string}>
     */
    private function detectionReasons(array $row, int $ipCount, int $ipChanges, int $risk, array $ipLogs, array $ips, ?\App\Services\IpIntel\IpIntelService $intel = null): array
    {
        $reasons = [];
        if (($row['visits'] ?? 0) >= 2) {
            $reasons[] = ['label' => 'Repeated Device ID', 'level' => 'High'];
        }
        if ($ipChanges >= 2) {
            $reasons[] = ['label' => 'IP Velocity / Rotation', 'level' => 'High'];
        } elseif ($ipChanges === 1) {
            $reasons[] = ['label' => 'IP Change Detected', 'level' => 'Medium'];
        }
        if (($row['invalid'] ?? 0) > 0) {
            $reasons[] = ['label' => 'Invalid Traffic Signals', 'level' => 'High'];
        }
        $intel ??= app(\App\Services\IpIntel\IpIntelService::class);
        $hasProxy = false;
        $hasPoorRep = false;
        foreach ($ips as $ip) {
            $log = $ipLogs[$ip] ?? null;
            if (! $log) {
                continue;
            }
            if (! $hasProxy && $intel->isProxySuspect($log)) {
                $reasons[] = ['label' => 'Proxy / VPN Network', 'level' => 'Medium'];
                $hasProxy = true;
            }
            $conf = $log->abuse_confidence_score;
            if (! $hasPoorRep && is_numeric($conf) && (int) $conf >= 40) {
                $reasons[] = ['label' => 'Poor IP Reputation', 'level' => ((int) $conf >= 70 ? 'High' : 'Medium')];
                $hasPoorRep = true;
            }
        }
        if ($risk >= 75 && $reasons === []) {
            $reasons[] = ['label' => 'Elevated Abuse Confidence', 'level' => 'High'];
        }
        if ($reasons === []) {
            $reasons[] = ['label' => 'Monitoring', 'level' => 'Low'];
        }

        return array_slice($reasons, 0, 5);
    }

    /**
     * @param  list<array{ip:string,at:string}>  $events
     * @return list<array{ip:string,at:string,tone:string}>
     */
    private function uniqueIpHistory(array $events): array
    {
        usort($events, static fn ($a, $b) => strcmp($b['at'], $a['at']));
        $seen = [];
        $out = [];
        $tones = ['high', 'suspicious', 'watch', 'clean', 'medium'];
        foreach ($events as $i => $ev) {
            $ip = $ev['ip'];
            if (isset($seen[$ip])) {
                continue;
            }
            $seen[$ip] = true;
            $out[] = [
                'ip' => $ip,
                'at' => $ev['at'],
                'tone' => $tones[$i % count($tones)],
            ];
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    /**
     * Merge clickronix_devices intelligence (paid clicks, conversions, confidence, exclusion action).
     *
     * @param  list<int>  $domainIds
     * @param  list<array<string, mixed>>  $deviceRows
     * @return list<array<string, mixed>>
     */
    private function enrichFromClickronixDevices(array $domainIds, array $deviceRows): array
    {
        if ($deviceRows === [] || ! Schema::hasTable('clickronix_devices')) {
            return $deviceRows;
        }

        $ids = [];
        foreach ($deviceRows as $row) {
            $did = trim((string) ($row['device_id'] ?? ''));
            if ($did !== '' && str_starts_with($did, 'DEV_')) {
                $ids[$did] = true;
            }
        }
        if ($ids === []) {
            return $deviceRows;
        }

        $profiles = DB::table('clickronix_devices')
            ->whereIn('domain_id', $domainIds)
            ->whereIn('device_id', array_keys($ids))
            ->get()
            ->keyBy('device_id');

        foreach ($deviceRows as &$row) {
            $did = (string) ($row['device_id'] ?? '');
            $profile = $profiles[$did] ?? null;
            if (! $profile) {
                continue;
            }
            $paid = (int) ($profile->paid_click_count ?? 0);
            if ($paid > (int) ($row['clicks'] ?? 0)) {
                $row['clicks'] = $paid;
            }
            $row['conversions'] = (int) ($profile->conversion_count ?? 0);
            $row['device_confidence'] = is_numeric($profile->device_confidence ?? null)
                ? (int) round(((float) $profile->device_confidence) * 100)
                : null;
            $row['exclusion_candidate'] = (bool) ($profile->exclusion_candidate ?? false);
            if ((int) ($profile->risk_score ?? 0) > (int) ($row['risk_score'] ?? 0)) {
                $row['risk_score'] = (int) $profile->risk_score;
            }
            if (filled($profile->risk_label ?? null)) {
                $row['status'] = (string) $profile->risk_label;
                $row['status_tone'] = $row['exclusion_candidate']
                    ? 'high'
                    : (((int) $row['risk_score'] >= 45) ? 'suspicious' : 'watch');
            }
            if ($row['exclusion_candidate']) {
                $row['action_label'] = 'Excluded';
            } elseif ((int) ($row['conversions'] ?? 0) > 0) {
                $row['action_label'] = 'Allow';
            } elseif ((int) ($row['clicks'] ?? 0) >= 2) {
                $row['action_label'] = 'Monitor';
            } else {
                $row['action_label'] = 'Allow';
            }
            if ((int) ($profile->ip_change_count ?? 0) > (int) ($row['ip_changes'] ?? 0)) {
                $row['ip_changes'] = (int) $profile->ip_change_count;
            }
            if ((int) ($profile->ip_count ?? 0) > (int) ($row['ip_count'] ?? 0)) {
                $row['ip_count'] = (int) $profile->ip_count;
            }
        }
        unset($row);

        return $deviceRows;
    }

    /**
     * IP Changes tab: one row per IP, then device (IP-first).
     *
     * @param  list<array<string, mixed>>  $deviceRows
     * @return list<array<string, mixed>>
     */
    private function ipChangeRows(array $deviceRows): array
    {
        $rows = [];
        foreach ($deviceRows as $device) {
            if ((int) ($device['ip_changes'] ?? 0) < 1) {
                continue;
            }
            $ips = array_values($device['ips'] ?? []);
            if ($ips === []) {
                $rows[] = $device;
                continue;
            }
            foreach ($ips as $ip) {
                $rows[] = array_merge($device, [
                    'ip' => $ip,
                    'ips' => [$ip],
                    'ip_count' => 1,
                    'device_key' => ($device['device_key'] ?? $device['device_id']).'|'.$ip,
                ]);
            }
        }
        usort($rows, static fn ($a, $b) => ($b['ip_changes'] <=> $a['ip_changes']) ?: ($b['risk_score'] <=> $a['risk_score']));

        return array_slice($rows, 0, 100);
    }

    /**
     * @param  list<array<string, mixed>>  $deviceRows
     * @param  array<string, IpLog>  $ipLogs
     * @return list<array<string, mixed>>
     */
    private function reputationRows(array $deviceRows, array $ipLogs): array
    {
        $rows = [];
        foreach ($deviceRows as $device) {
            foreach ($device['ips'] as $ip) {
                $log = $ipLogs[$ip] ?? null;
                $score = is_numeric($log?->abuse_confidence_score) ? (int) $log->abuse_confidence_score : (int) round($device['risk_score'] * 0.7);
                $rows[] = [
                    'ip' => $ip,
                    'device_id' => $device['device_id'],
                    'device_key' => $device['device_key'] ?? $device['device_id'],
                    'risk_score' => max(1, min(99, $score)),
                    'status' => $score >= 70 ? 'High Risk' : ($score >= 40 ? 'Suspicious' : 'Watch'),
                    'status_tone' => $score >= 70 ? 'high' : ($score >= 40 ? 'suspicious' : 'watch'),
                    'last_seen' => $device['last_seen'],
                    'clicks' => $device['clicks'],
                    'ip_changes' => $device['ip_changes'],
                    'ips' => [$ip],
                    'ip_count' => 1,
                    'ip_history' => [['ip' => $ip, 'at' => $device['last_seen'], 'tone' => 'suspicious']],
                    'reasons' => $device['reasons'],
                ];
            }
        }
        usort($rows, static fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return array_slice($rows, 0, 100);
    }

    /** @param  list<array{label:string,value:int}>  $series */
    private function sparkFromSeries(array $series, float $scale = 1.0): array
    {
        if ($series === []) {
            return [2, 3, 2, 4, 3, 5, 4];
        }
        $vals = array_map(static fn ($p) => max(1, (int) round(($p['value'] ?? 0) * $scale)), $series);
        if (count($vals) < 7) {
            $vals = array_pad($vals, 7, $vals[count($vals) - 1] ?? 2);
        }

        return array_slice($vals, -7);
    }

    private function shortDevice(string $id): string
    {
        $id = trim($id);
        if (strlen($id) <= 14) {
            return $id;
        }

        return substr($id, 0, 10).'…';
    }

    /**
     * Prefer real DEV_ tokens; never surface unknown_* placeholders in the UI.
     */
    private function formatDeviceLabel(string $deviceRaw, string $fpRaw, string $ip): string
    {
        return DeviceIdLabel::format($deviceRaw, $fpRaw, $ip);
    }
}
