<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Support\DetectionPlanFeatures;
use App\Support\MonthlyReportSnapshot;
use App\Support\PageAnalyticsAggregator;
use App\Support\SettingsDataReportCatalog;
use App\Support\UserTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → Data Reports unified download (PDF §7).
 */
class SettingsDataReportController extends Controller
{
    public function download(Request $request): Response
    {
        $type = SettingsDataReportCatalog::normalizeType($request->query('report_type', $request->query('group')));
        $format = SettingsDataReportCatalog::normalizeFormat($request->query('format'));
        $columnGroup = trim((string) $request->query('column_group', ''));

        // Designed PDF summary (HTML print layout) for any report type.
        if ($format === 'pdf') {
            return $this->designedPdfSummary($request, $type);
        }

        return match ($type) {
            SettingsDataReportCatalog::TYPE_ANALYTICS_DASHBOARD => $this->analyticsExport($request, $format),
            SettingsDataReportCatalog::TYPE_TRAFFIC_CONTROL => $this->forwardBotExport($request, 'traffic-control', $format),
            SettingsDataReportCatalog::TYPE_DETECTION_SESSION => $this->detectionExport($request, $format, $columnGroup),
            default => $this->paidAdvertisingExport($request, $format, $columnGroup),
        };
    }

    private function paidAdvertisingExport(Request $request, string $format, string $columnGroup): Response
    {
        $query = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'column_group' => $columnGroup !== '' ? $columnGroup : null,
            'ad_group' => $request->query('ad_group'),
            'use_utc' => $request->boolean('use_utc') ? '1' : null,
            'timezone' => $request->boolean('use_utc') ? 'UTC' : null,
            'domain_id' => $request->query('domain_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $forward = Request::create(
            $format === 'xlsx'
                ? route('paid-marketing.detailed-export-xlsx', $query)
                : route('paid-marketing.detailed-export', $query),
            'GET',
            $query
        );
        $forward->setUserResolver(fn () => $request->user());

        return $format === 'xlsx'
            ? app(PaidMarketingController::class)->exportDetailedXlsx($forward)
            : app(PaidMarketingController::class)->exportDetailedCsv($forward);
    }

    private function analyticsExport(Request $request, string $format): Response
    {
        $query = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'format' => $format === 'xlsx' ? 'csv' : $format,
            'use_utc' => $request->boolean('use_utc') ? '1' : null,
            'timezone' => $request->boolean('use_utc') ? 'UTC' : null,
            'domain_id' => $request->query('domain_id'),
        ], fn ($v) => $v !== null && $v !== '');

        if ($format === 'xlsx') {
            // Analytics summary XLSX: KPI sheet via PhpSpreadsheet when available.
            return $this->analyticsXlsx($request);
        }

        $forward = Request::create(url('/bot-protection/page-analytics/export'), 'GET', $query);
        $forward->setUserResolver(fn () => $request->user());

        return app(BotProtectionController::class)->pageAnalyticsExport($forward);
    }

    private function forwardBotExport(Request $request, string $source, string $format): Response
    {
        if ($format === 'xlsx' && class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return $this->trafficControlXlsx($request, $source);
        }

        $query = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'source' => $source,
            'use_utc' => $request->boolean('use_utc') ? '1' : null,
            'timezone' => $request->boolean('use_utc') ? 'UTC' : null,
            'domain_id' => $request->query('domain_id'),
            'ad_group' => $request->query('ad_group'),
        ], fn ($v) => $v !== null && $v !== '');

        $forward = Request::create(route('bot-protection.export', $query), 'GET', $query);
        $forward->setUserResolver(fn () => $request->user());

        return app(BotProtectionController::class)->exportCsv($forward);
    }

    private function trafficControlXlsx(Request $request, string $source): Response
    {
        $user = $request->user();
        $domainIds = Domain::query()
            ->where('user_id', $user->id)
            ->forBotProtection()
            ->when((int) $request->query('domain_id', 0) > 0, fn ($q) => $q->where('id', (int) $request->query('domain_id')))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        [$from, $to] = UserTimezone::dateRangeFromRequest($request, $user);
        $result = app(\App\Support\TrafficControlSessionQuery::class)->paginate($domainIds, $from, $to, $request, 1, 5000);

        $headers = [
            'Visitor IP', 'Session ID', 'Device ID', 'Source / Platform', 'Campaign', 'Keyword', 'Headline',
            'Landing Page', 'Page Flow', 'First Seen', 'Last Seen', 'Time on Site', 'Page Views',
            'CTA Clicks', 'Tel Clicks', 'Form Starts', 'Add to Cart', 'Checkout', 'Purchase', 'Revenue',
            'Device', 'Browser', 'OS', 'Country', 'Crawler Score', 'Automation Score', 'Malicious Score', 'Exit Page',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($source === 'traffic-control' ? 'Traffic Control' : 'Sessions');
        $sheet->fromArray($headers, null, 'A1');
        $rowNum = 2;
        foreach ($result['data'] as $row) {
            $sheet->fromArray([[
                $row['ip'] ?? '',
                $row['session_id'] ?? '',
                $row['fingerprint_id'] ?? '',
                $row['source_platform'] ?? '',
                $row['campaign'] ?? '',
                $row['keyword'] ?? '',
                $row['headline'] ?? '',
                $row['landing_page'] ?? '',
                $row['page_flow'] ?? '',
                $row['first_seen'] ?? '',
                $row['last_seen'] ?? '',
                $row['time_on_site'] ?? '',
                $row['page_views'] ?? 0,
                $row['cta_clicks'] ?? 0,
                $row['tel_clicks'] ?? 0,
                $row['form_starts'] ?? 0,
                $row['add_to_cart'] ?? 0,
                $row['checkout'] ?? 0,
                $row['purchase'] ?? '',
                $row['revenue'] ?? '',
                $row['device'] ?? '',
                $row['browser'] ?? '',
                $row['os'] ?? '',
                $row['country'] ?? '',
                $row['crawler_score'] ?? '',
                $row['automation_score'] ?? '',
                $row['malicious_score'] ?? '',
                $row['exit_page'] ?? '',
            ]], null, 'A'.$rowNum);
            $rowNum++;
        }

        $filename = 'traffic-control-'.$from->toDateString().'-'.$to->toDateString().'.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'pmx-xlsx-');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $bytes = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function detectionExport(Request $request, string $format, string $columnGroup): Response
    {
        // Prefer paid detailed export when a column group is selected (fraud/events/etc).
        if ($columnGroup !== '') {
            return $this->paidAdvertisingExport($request, $format === 'pdf' ? 'csv' : $format, $columnGroup);
        }

        return $this->forwardBotExport($request, 'traffic-control', $format);
    }

    private function analyticsXlsx(Request $request): Response
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return $this->analyticsExport($request, 'csv');
        }

        $user = $request->user();
        $domainIds = Domain::query()
            ->where('user_id', $user->id)
            ->forBotProtection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        [$from, $to] = UserTimezone::dateRangeFromRequest($request, $user);
        $payload = app(PageAnalyticsAggregator::class)->build($domainIds, $from, $to);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analytics');
        $sheet->fromArray(['Metric', 'Value'], null, 'A1');
        $row = 2;
        foreach (($payload['kpis'] ?? []) as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $sheet->fromArray([$key, $value], null, 'A'.$row);
            $row++;
        }
        $conv = $payload['conversion_summary'] ?? [];
        foreach ($conv as $key => $value) {
            if (str_ends_with((string) $key, '_raw')) {
                continue;
            }
            $sheet->fromArray(['conversion_'.$key, $value], null, 'A'.$row);
            $row++;
        }

        $filename = 'analytics-dashboard-'.$from->toDateString().'-'.$to->toDateString().'.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'pmx-xlsx-');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $bytes = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function designedPdfSummary(Request $request, string $type): Response
    {
        $user = $request->user();
        $domainIds = Domain::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        [$from, $to] = UserTimezone::dateRangeFromRequest($request, $user);
        $tz = $request->boolean('use_utc') ? 'UTC' : UserTimezone::forUser($user);

        $analytics = app(PageAnalyticsAggregator::class)->build($domainIds, $from, $to);
        $paid = MonthlyReportSnapshot::paid($domainIds, $from, $to);
        $prevFrom = $from->copy()->subDays(max(1, $from->diffInDays($to) + 1));
        $prevTo = $from->copy()->subSecond();
        $previous = app(PageAnalyticsAggregator::class)->build($domainIds, $prevFrom, $prevTo);

        $crossDomainEnabled = DetectionPlanFeatures::enabled($user, DetectionPlanFeatures::SESSION_RECORDINGS)
            || DetectionPlanFeatures::enabled($user, DetectionPlanFeatures::BEHAVIOR_CONTROL);

        $html = view('reports.designed-summary', [
            'user' => $user,
            'from' => $from->copy()->timezone($tz),
            'to' => $to->copy()->timezone($tz),
            'timezoneLabel' => $tz,
            'reportType' => SettingsDataReportCatalog::REPORT_TYPES[$type]['label'] ?? $type,
            'payload' => $analytics,
            'previous' => $previous,
            'paid' => $paid,
            'crossDomainEnabled' => $crossDomainEnabled,
            'exportMode' => true,
        ])->render();

        $filename = 'clickronix-report-'.$from->toDateString().'-'.$to->toDateString().'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-Clickronix-Report' => 'designed-pdf-summary',
        ]);
    }
}
