<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Models\PaidMarketingVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaidMarketingController extends Controller
{
    public function detailedView(Request $request): View
    {
        $query = PaidMarketingVisit::query()
            ->with(['domain', 'clicks' => function ($q) {
            $q->orderBy('clicked_at');
        }])
            ->select('paid_marketing_visits.*')
            ->selectSub(
                IpLog::query()
                    ->select('is_blocked')
                    ->whereColumn('ip_logs.ip', 'paid_marketing_visits.ip')
                    ->limit(1),
                'ip_is_blocked'
            );

        if ($ip = $request->string('ip')->toString()) {
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        if ($path = $request->string('path')->toString()) {
            $query->where('last_path', 'like', '%' . $path . '%');
        }

        if ($platform = $request->string('platform')->toString()) {
            $query->where('platform', $platform);
        }

        if ($from = $request->date('from')) {
            $query->whereDate('last_click_at', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $query->whereDate('last_click_at', '<=', $to);
        }

        $visits = $query->orderByDesc('last_click_at')->paginate(25)->withQueryString();

        $platforms = PaidMarketingVisit::query()
            ->select('platform')
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');

        return view('paid-marketing.detailed-view', [
            'visits' => $visits,
            'platforms' => $platforms,
        ]);
    }

    public function detectionSettings(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->get();

        $selectedDomainId = (int) $request->integer('domain_id');
        $domain = $domains->firstWhere('id', $selectedDomainId) ?? $domains->first();

        $settings = null;
        if ($domain) {
            $settings = DomainDetectionSetting::firstOrCreate(
                ['domain_id' => $domain->id],
                [
                    'invalid_bot_action' => 'block',
                    'invalid_malicious_action' => 'block',
                    'suspicious_enabled' => true,
                    'suspicious_matrix' => [
                        'vpn' => 'allow',
                        'proxy' => 'block',
                        'data_center' => 'block',
                        'abnormal_rate_limit' => 'allow',
                    ],
                    'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
                ]
            );
        }

        return view('paid-marketing.detection-settings', [
            'domains' => $domains,
            'domain' => $domain,
            'settings' => $settings,
        ]);
    }

    public function updateDetectionSettings(Request $request, Domain $domain): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'invalid_bot_action' => ['required', 'in:allow,block'],
            'invalid_malicious_action' => ['required', 'in:allow,block'],
            'suspicious_enabled' => ['nullable', 'boolean'],
            'suspicious_vpn' => ['required', 'in:allow,block'],
            'suspicious_proxy' => ['required', 'in:allow,block'],
            'suspicious_data_center' => ['required', 'in:allow,block'],
            'suspicious_abnormal_rate_limit' => ['required', 'in:allow,block'],
            'session_recordings' => ['nullable', 'boolean'],
            'frequency_capping' => ['nullable', 'boolean'],
            'out_of_geo_enabled' => ['nullable', 'boolean'],
            'out_of_geo_countries' => ['nullable', 'string'],
            'allow_list_enabled' => ['nullable', 'boolean'],
            'allow_list_ips' => ['nullable', 'string'],
            'audience_exclusion_event' => ['required', 'in:exclude_all_threat_groups_auto,exclude_bot_malicious_only,disable_auto_exclusions'],
        ]);

        $countries = collect(explode(',', (string) ($data['out_of_geo_countries'] ?? '')))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();

        DomainDetectionSetting::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => $data['invalid_bot_action'],
                'invalid_malicious_action' => $data['invalid_malicious_action'],
                'suspicious_enabled' => (bool) ($data['suspicious_enabled'] ?? false),
                'suspicious_matrix' => [
                    'vpn' => $data['suspicious_vpn'],
                    'proxy' => $data['suspicious_proxy'],
                    'data_center' => $data['suspicious_data_center'],
                    'abnormal_rate_limit' => $data['suspicious_abnormal_rate_limit'],
                ],
                'session_recordings' => (bool) ($data['session_recordings'] ?? false),
                'frequency_capping' => (bool) ($data['frequency_capping'] ?? false),
                'out_of_geo_enabled' => (bool) ($data['out_of_geo_enabled'] ?? false),
                'out_of_geo_countries' => $countries,
                'allow_list_enabled' => (bool) ($data['allow_list_enabled'] ?? false),
                'allow_list_ips' => $data['allow_list_ips'] ?? null,
                'audience_exclusion_event' => $data['audience_exclusion_event'],
            ]
        );

        return redirect()
            ->route('paid-marketing.detection-settings', ['domain_id' => $domain->id])
            ->with('status', 'Detection settings saved.');
    }
}

