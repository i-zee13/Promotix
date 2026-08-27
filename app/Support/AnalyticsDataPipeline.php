<?php

namespace App\Support;

/**
 * Recommended Analytics data pipeline stages (PDF §10).
 * Documents the intended end-to-end flow already wired in the app.
 */
class AnalyticsDataPipeline
{
    /**
     * @var list<array{key: string, title: string, components: list<string>}>
     */
    public const STAGES = [
        [
            'key' => 'tracking_tag',
            'title' => 'Tracking Tag',
            'components' => ['Page load', 'source/referrer', 'campaign', 'device/session initialization'],
        ],
        [
            'key' => 'event_collector',
            'title' => 'Event Collector',
            'components' => ['page_view', 'scroll', 'CTA', 'tel', 'form', 'cart', 'checkout', 'purchase'],
        ],
        [
            'key' => 'session_service',
            'title' => 'Session Service',
            'components' => ['entry/exit', 'page flow', 'time on site', 'visitor/device association'],
        ],
        [
            'key' => 'enrichment',
            'title' => 'Enrichment',
            'components' => ['geo', 'browser/OS', 'referrer/platform', 'keyword/headline', 'quality signals'],
        ],
        [
            'key' => 'analytics_store',
            'title' => 'Analytics Store',
            'components' => ['raw events', 'session aggregates', 'daily aggregates'],
        ],
        [
            'key' => 'analytics_dashboard',
            'title' => 'Analytics Dashboard',
            'components' => ['KPIs', 'source overview', 'pages', 'funnel', 'revenue', 'quality'],
        ],
        [
            'key' => 'traffic_control',
            'title' => 'Traffic Control',
            'components' => ['row-level visitor/session drilldown', 'event timeline'],
        ],
        [
            'key' => 'reports',
            'title' => 'Reports',
            'components' => ['date-filtered export', 'monthly designed email/PDF'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function stageKeys(): array
    {
        return array_column(self::STAGES, 'key');
    }
}
