<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use App\Models\Domain;
use App\Services\ClickronixDeviceService;
use App\Support\DomainKeyHostGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GTM / GA4 → Clickronix conversion webhook (spec §5–6).
 *
 * POST /api/v1/conversion
 * { client_id, gclid?, conversion, timestamp?, domainKey|domain_key }
 */
class ConversionController extends Controller
{
    use ResolvesClientIp;

    public function store(Request $request, ClickronixDeviceService $devices): Response
    {
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $data = $request->validate([
            'domainKey' => ['nullable', 'string', 'max:64'],
            'domain_key' => ['nullable', 'string', 'max:64'],
            'client_id' => ['nullable', 'string', 'max:128'],
            'ga4_client_id' => ['nullable', 'string', 'max:128'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:64'],
            'conversion' => ['nullable', 'string', 'max:64'],
            'conversion_type' => ['nullable', 'string', 'max:64'],
            'timestamp' => ['nullable'],
        ]);

        $domainKey = trim((string) ($data['domainKey'] ?? $data['domain_key'] ?? ''));
        if ($domainKey === '') {
            return $this->cors($request, response()->json(['ok' => false, 'message' => 'domainKey is required.'], 422));
        }

        $domain = Domain::query()->where('domain_key', $domainKey)->first();
        if (! $domain) {
            return $this->cors($request, response()->json(['ok' => false, 'message' => 'Unknown domainKey.'], 404));
        }

        // Browser/GTM posts include Origin/Referer — enforce host binding.
        // Server-side webhooks may send domainKey only (no browser headers).
        $origin = trim((string) $request->headers->get('Origin', ''));
        $referer = trim((string) $request->headers->get('Referer', ''));
        if (($origin !== '' && strcasecmp($origin, 'null') !== 0) || $referer !== '') {
            if ($reason = DomainKeyHostGuard::mismatchReason($request, $domain)) {
                return $this->cors($request, response()->json(['ok' => false, 'message' => $reason], 403));
            }
        }

        $clientId = trim((string) ($data['client_id'] ?? $data['ga4_client_id'] ?? ''));
        $gclid = trim((string) ($data['gclid'] ?? ''));
        $deviceId = trim((string) ($data['device_id'] ?? ''));
        if ($clientId === '' && $gclid === '' && $deviceId === '') {
            return $this->cors($request, response()->json([
                'ok' => false,
                'message' => 'Provide client_id (GA4), gclid, or device_id to match the visitor.',
            ], 422));
        }

        $result = $devices->recordConversion($domain, [
            'client_id' => $clientId,
            'gclid' => $gclid,
            'device_id' => $deviceId,
            'conversion' => $data['conversion'] ?? $data['conversion_type'] ?? 'lead',
            'timestamp' => $data['timestamp'] ?? null,
        ]);

        return $this->cors(
            $request,
            response()->json($result, ($result['ok'] ?? false) ? 200 : 404)
        );
    }
}
