<?php

namespace App\Support;

class PermissionDescription
{
    /**
     * Human-readable access scope for the Roles & Permissions UI.
     */
    public static function for(string $slug, ?string $routeName = null): string
    {
        return self::DESCRIPTIONS[$slug]
            ?? ($routeName
                ? "Allows access to the {$routeName} area and its permitted actions."
                : 'Allows access to this product area and its permitted actions.');
    }

    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'automation' => 'Create, edit, run, and monitor automation workflows.',
        'bot-protection' => 'View bot-protection dashboards, detections, and advanced traffic records.',
        'dashboard' => 'View the main dashboard and high-level account metrics.',
        'domain-management' => 'Add, configure, verify, and remove website domains.',
        'integrations' => 'View and manage third-party service integrations.',
        'ip-logs' => 'View IP intelligence records and manage IP block actions.',
        'paid-marketing-dashboard' => 'View Google Ads performance, invalid-click, and IP-risk summaries.',
        'paid-marketing-detailed' => 'View, filter, export, and investigate individual paid-traffic visits.',
        'paid-marketing-detection-settings' => 'Change paid-ad detection rules, targeting, and exclusion settings.',
        'paid-marketing-platform-connections' => 'Connect Google Ads and manage linked advertising accounts.',
        'roles' => 'Create, edit, assign permissions to, and remove roles.',
        'security-logs' => 'View security events, audit activity, and authentication-related logs.',
        'support-system' => 'View, create, and manage customer support requests.',
        'system-settings' => 'Change global system configuration and feature settings.',
        'traffic-bot-logs' => 'View traffic and bot-detection logs across the platform.',
        'upgrade-plan' => 'View plans, invoices, billing methods, and manage subscription changes.',
        'users' => 'View users and assign their roles.',
    ];
}
