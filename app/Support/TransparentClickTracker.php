<?php

namespace App\Support;

use App\Models\ClickTrackerEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Google Transparent Click Tracker layer (parallel to landing-tag fraud).
 * /click never runs fingerprint or bot scoring.
 */
final class TransparentClickTracker
{
    /** @var list<string> */
    public const REGISTRY_KEYS = [
        'cx_account',
        'cx_campaign',
        'cx_adgroup',
        'cx_creative',
        'cx_keyword',
        'cx_device',
        'cx_network',
        'cx_matchtype',
        'cx_placement',
    ];

    public static function mintId(): string
    {
        return 'CXTRK_'.strtoupper(bin2hex(random_bytes(8)));
    }

    public static function baseUrl(?string $override = null): string
    {
        if ($override !== null && trim($override) !== '') {
            return rtrim($override, '/');
        }

        $configured = rtrim((string) config('click-tracker.url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function landingUrl(Request $request): string
    {
        foreach (['redirect', 'final_url', 'url', 'lpurl'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public static function registryFromRequest(Request $request): array
    {
        $out = [];
        foreach (self::REGISTRY_KEYS as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $out[$key] = Str::limit($value, 240, '');
            }
        }

        $aliases = [
            'cx_campaign' => ['campaign_id', 'campaignid', 'gad_campaignid'],
            'cx_adgroup' => ['adgroup_id', 'adgroupid'],
            'cx_creative' => ['creative'],
            'cx_keyword' => ['keyword'],
            'cx_device' => ['device'],
            'cx_network' => ['network'],
            'cx_matchtype' => ['matchtype'],
            'cx_placement' => ['placement'],
        ];
        foreach ($aliases as $cxKey => $from) {
            if (isset($out[$cxKey])) {
                continue;
            }
            foreach ($from as $alias) {
                $value = trim((string) $request->query($alias, ''));
                if ($value !== '') {
                    $out[$cxKey] = Str::limit($value, 240, '');
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{click_id: ?string, click_id_type: ?string}
     */
    public static function clickIdFromParams(array $params): array
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $type) {
            $value = trim((string) ($params[$type] ?? ''));
            if ($value !== '') {
                return ['click_id' => $value, 'click_id_type' => $type];
            }
        }

        return ['click_id' => null, 'click_id_type' => null];
    }

    public static function cxtrkFromPayload(array $data): ?string
    {
        $direct = strtoupper(trim((string) ($data['cxtrk'] ?? '')));
        if (str_starts_with($direct, 'CXTRK_')) {
            return $direct;
        }

        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '') {
            return null;
        }
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return null;
        }
        parse_str($query, $parsed);
        $fromUrl = strtoupper(trim((string) ($parsed['cxtrk'] ?? '')));

        return str_starts_with($fromUrl, 'CXTRK_') ? $fromUrl : null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function record(
        Request $request,
        array $params,
        string $landingUrl,
        string $cxtrkId,
        ?int $domainId,
        string $ip,
    ): ?ClickTrackerEvent {
        if (! Schema::hasTable('click_tracker_events')) {
            return null;
        }

        $click = self::clickIdFromParams($params);
        $registry = self::registryFromRequest($request);

        return ClickTrackerEvent::query()->create([
            'cxtrk_id' => $cxtrkId,
            'domain_id' => $domainId,
            'landing_url' => Str::limit($landingUrl, 2000, ''),
            'click_id' => $click['click_id'],
            'click_id_type' => $click['click_id_type'],
            'ip' => Str::limit($ip, 64, ''),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'cx_account' => $registry['cx_account'] ?? null,
            'cx_campaign' => $registry['cx_campaign'] ?? null,
            'cx_adgroup' => $registry['cx_adgroup'] ?? null,
            'cx_creative' => $registry['cx_creative'] ?? null,
            'cx_keyword' => $registry['cx_keyword'] ?? null,
            'cx_registry' => $registry,
            'tracked_at' => now(),
        ]);
    }

    /**
     * Link a landing-page visit (tag ingest) back to the /click audit row.
     *
     * @param  array<string, mixed>  $data
     */
    public static function joinLandingVisit(int $domainId, int $visitId, array $data): void
    {
        if (! Schema::hasTable('click_tracker_events') || $visitId <= 0) {
            return;
        }

        $cxtrk = self::cxtrkFromPayload($data);
        $now = now();

        if ($cxtrk) {
            ClickTrackerEvent::query()
                ->where('cxtrk_id', $cxtrk)
                ->whereNull('landing_visit_id')
                ->update([
                    'landing_visit_id' => $visitId,
                    'domain_id' => $domainId,
                    'joined_at' => $now,
                    'updated_at' => $now,
                ]);

            return;
        }

        foreach (['gclid', 'gbraid', 'wbraid'] as $type) {
            $id = trim((string) ($data[$type] ?? ''));
            if ($id === '') {
                continue;
            }

            $row = ClickTrackerEvent::query()
                ->where('click_id', $id)
                ->where(function ($q) use ($domainId): void {
                    $q->where('domain_id', $domainId)->orWhereNull('domain_id');
                })
                ->whereNull('landing_visit_id')
                ->orderByDesc('id')
                ->first();

            if ($row) {
                $row->fill([
                    'landing_visit_id' => $visitId,
                    'domain_id' => $domainId,
                    'joined_at' => $now,
                ])->save();

                return;
            }
        }
    }
}
