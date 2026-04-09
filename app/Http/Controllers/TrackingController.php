<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\IpLog;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function collect(Request $request)
    {
        // Handle CORS preflight
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $data = $request->validate([
            'domainKey' => ['required', 'string'],
            'type' => ['nullable', 'string'],
            'url' => ['nullable', 'string'],
            'path' => ['nullable', 'string'],
            'referrer' => ['nullable', 'string'],
            'gclid' => ['nullable', 'string'],
            'utm_source' => ['nullable', 'string'],
            'utm_medium' => ['nullable', 'string'],
            'utm_campaign' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string'],
        ]);

        $domain = Domain::where('domain_key', $data['domainKey'])->firstOrFail();

        $ip = $request->ip();
        $ua = $request->userAgent() ?? '';

        // Mark domain as connected/seen
        $domain->last_seen_at = now();
        $domain->tag_connected = true;
        $domain->save();

        // Log IP into existing ip_logs table
        $ipLog = IpLog::firstOrNew(['ip' => $ip]);
        if (! $ipLog->exists) {
            $ipLog->hits = 0;
        }
        $ipLog->hits = ($ipLog->hits ?? 0) + 1;
        $ipLog->user_agent = $ua;
        $ipLog->last_seen_at = now();
        $ipLog->last_path = $data['path'] ?? null;
        $ipLog->last_referrer = $data['referrer'] ?? null;
        $ipLog->save();

        // Paid marketing visit row (1 row per domain+ip)
        $visit = PaidMarketingVisit::firstOrNew([
            'domain_id' => $domain->id,
            'ip' => $ip,
        ]);
        if (! $visit->exists) {
            $visit->visits = 0;
        }
        $visit->visits = ($visit->visits ?? 0) + 1;
        $visit->last_click_at = now();
        $visit->last_path = $data['path'] ?? null;
        $visit->campaign = $data['utm_campaign'] ?? null;
        $visit->platform = $this->platformFromUa($ua);
        $visit->save();

        // Click detail entry (used by the modal list)
        $browser = $this->browserFromUa($ua);
        $os = $this->osFromUa($ua);

        PaidMarketingClick::create([
            'paid_marketing_visit_id' => $visit->id,
            'clicked_at' => now(),
            'ip' => $ip,
            'last_click_at' => now(),
            'campaign' => $data['utm_campaign'] ?? null,
            'paid_id' => $data['gclid'] ?? null,
            'path' => $data['url'] ?? ($data['path'] ?? null),
            'keyword' => $data['keyword'] ?? null,
            'browser_name' => $browser['name'],
            'browser_version' => $browser['version'],
            'os' => $os,
        ]);

        return $this->cors($request, response()->json(['ok' => true]));
    }

    protected function cors(Request $request, $response)
    {
        $origin = $request->headers->get('Origin');
        $allowOrigin = $origin ?: '*';

        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Vary', 'Origin')
            ->header('Access-Control-Allow-Credentials', $origin ? 'true' : 'false')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Max-Age', '86400');
    }

    private function platformFromUa(string $ua): ?string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'mobile')) return 'Mobile';
        return 'Desktop';
    }

    private function osFromUa(string $ua): ?string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'windows')) return 'Windows';
        if (str_contains($uaLower, 'mac os') || str_contains($uaLower, 'macintosh')) return 'Mac';
        if (str_contains($uaLower, 'android')) return 'Android';
        if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) return 'iOS';
        if (str_contains($uaLower, 'linux')) return 'Linux';
        return null;
    }

    private function browserFromUa(string $ua): array
    {
        // Very lightweight parsing (good enough for MVP UI).
        $patterns = [
            'Chrome' => '/Chrome\\/([0-9\\.]+)/',
            'Edge' => '/Edg\\/([0-9\\.]+)/',
            'Firefox' => '/Firefox\\/([0-9\\.]+)/',
            'Safari' => '/Version\\/([0-9\\.]+).*Safari/',
        ];
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $ua, $m)) {
                return ['name' => $name, 'version' => $m[1] ?? null];
            }
        }
        return ['name' => null, 'version' => null];
    }
}

