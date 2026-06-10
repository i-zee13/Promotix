<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use App\Models\Domain;
use App\Services\IpIntel\VisitProtectionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpFilterController extends Controller
{
    use ResolvesClientIp;

    /**
     * Lightweight gate for embedded scripts / plugins.
     * Returns allowed=false when the visitor IP is blocked or fails fraud checks.
     */
    public function check(Request $request): Response
    {
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $domainKey = (string) ($request->input('domainKey') ?: $request->input('domain_key') ?: '');
        $ip = $this->clientIp($request);
        $userAgent = $request->userAgent() ?? '';
        $country = $request->headers->get('CF-IPCountry') ?: null;

        $protection = app(VisitProtectionService::class);
        $ipLog = $protection->touchIpLog(
            $ip,
            $userAgent,
            $request->input('path'),
            $request->input('referrer'),
        );

        if ($domainKey === '') {
            $allowed = ! $ipLog->is_blocked;

            return $this->cors($request, response()->json([
                'allowed' => $allowed,
                'blocked' => ! $allowed,
            ]));
        }

        $domain = Domain::where('domain_key', $domainKey)->first();
        if (! $domain || ($domain->status ?? 'pending') === 'disabled') {
            return $this->cors($request, response()->json([
                'allowed' => true,
                'blocked' => false,
                'skipped' => 'unknown_domain',
            ]));
        }

        $isCrawler = $this->isCrawlerUa($userAgent);
        $assessment = $protection->assess($domain, $ipLog, $country, null, $isCrawler);
        $enforceBlock = $assessment['enforce_block'];

        return $this->cors($request, response()->json(array_merge(
            $protection->clientPayload($assessment['detection'], $enforceBlock),
            [
                'allowed' => ! $enforceBlock,
            ],
        )));
    }

    private function isCrawlerUa(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        $needles = [
            'Googlebot', 'bingbot', 'Slurp', 'DuckDuckBot', 'YandexBot', 'Baiduspider',
            'facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'Applebot', 'AhrefsBot',
            'SemrushBot', 'MJ12bot', 'PetalBot', 'Bytespider', 'GPTBot', 'ClaudeBot',
        ];

        foreach ($needles as $needle) {
            if (stripos($ua, $needle) !== false) {
                return true;
            }
        }

        return preg_match('/(crawler|spider|bot)\\b/i', $ua) === 1;
    }
}
