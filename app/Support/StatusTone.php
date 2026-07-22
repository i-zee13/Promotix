<?php

namespace App\Support;

class StatusTone
{
    public static function pillClass(string $tone): string
    {
        return 'is-tone-'.$tone;
    }

    public static function user(string $status): string
    {
        return match ($status) {
            'active' => 'active',
            'suspended' => 'suspended',
            'pending' => 'expiry',
            'banned' => 'ban',
            default => 'deactivated',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function userFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Statuses', 'tone' => 'all'],
            ['value' => 'active', 'label' => 'Active', 'tone' => 'active'],
            ['value' => 'suspended', 'label' => 'Suspended', 'tone' => 'suspended'],
            ['value' => 'blocked', 'label' => 'Block', 'tone' => 'blocked'],
            ['value' => 'deactivated', 'label' => 'Deactivate', 'tone' => 'deactivated'],
            ['value' => 'expiry', 'label' => 'Expiry', 'tone' => 'expiry'],
            ['value' => 'banned', 'label' => 'Ban', 'tone' => 'ban'],
        ];
    }

    public static function subscription(string $status): string
    {
        return match ($status) {
            'active' => 'active',
            'trialing' => 'expiry',
            'pending' => 'expiry',
            'past_due' => 'blocked',
            'paused' => 'suspended',
            'cancelled' => 'ban',
            default => 'deactivated',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function subscriptionFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Statuses', 'tone' => 'all'],
            ['value' => 'active', 'label' => 'Active', 'tone' => 'active'],
            ['value' => 'pending', 'label' => 'Pending', 'tone' => 'expiry'],
            ['value' => 'past_due', 'label' => 'Payment Failed', 'tone' => 'blocked'],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'tone' => 'ban'],
            ['value' => 'paused', 'label' => 'Paused', 'tone' => 'suspended'],
            ['value' => 'trialing', 'label' => 'Trialing', 'tone' => 'expiry'],
        ];
    }

    public static function ticket(string $status): string
    {
        return match (strtolower($status)) {
            'open' => 'open',
            'in_progress' => 'in_progress',
            'waiting' => 'waiting',
            'resolved' => 'resolved',
            'closed' => 'closed',
            default => 'deactivated',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function ticketFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Statuses', 'tone' => 'all'],
            ['value' => 'open', 'label' => 'Open', 'tone' => 'open'],
            ['value' => 'in_progress', 'label' => 'In Progress', 'tone' => 'in_progress'],
            ['value' => 'resolved', 'label' => 'Resolved', 'tone' => 'resolved'],
            ['value' => 'closed', 'label' => 'Closed', 'tone' => 'closed'],
            ['value' => 'waiting', 'label' => 'Waiting', 'tone' => 'waiting'],
        ];
    }

    public static function ticketPriority(string $priority): string
    {
        return match (strtolower($priority)) {
            'urgent' => 'ban',
            'high' => 'blocked',
            'low' => 'deactivated',
            default => 'active',
        };
    }

    public static function payment(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'active',
            'pending' => 'expiry',
            'failed', 'rejected' => 'ban',
            'refunded' => 'deactivated',
            default => 'deactivated',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function paymentFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Statuses', 'tone' => 'all'],
            ['value' => 'paid', 'label' => 'Paid', 'tone' => 'active'],
            ['value' => 'pending', 'label' => 'Pending', 'tone' => 'expiry'],
            ['value' => 'failed', 'label' => 'Failed', 'tone' => 'ban'],
            ['value' => 'rejected', 'label' => 'Rejected', 'tone' => 'ban'],
            ['value' => 'refunded', 'label' => 'Refunded', 'tone' => 'deactivated'],
        ];
    }

    public static function traffic(string $statusLabel): string
    {
        return match ($statusLabel) {
            'Blocked' => 'blocked',
            'Flagged' => 'suspended',
            default => 'active',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function trafficFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Statuses', 'tone' => 'all'],
            ['value' => 'allow', 'label' => 'Allowed', 'tone' => 'active'],
            ['value' => 'flag', 'label' => 'Flagged', 'tone' => 'suspended'],
            ['value' => 'block', 'label' => 'Blocked', 'tone' => 'blocked'],
        ];
    }

    public static function product(bool $isActive): string
    {
        return $isActive ? 'active' : 'deactivated';
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function productFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Products', 'tone' => 'all'],
            ['value' => 'active', 'label' => 'Active', 'tone' => 'active'],
            ['value' => 'inactive', 'label' => 'Deactivate', 'tone' => 'deactivated'],
        ];
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function planFilters(): array
    {
        return [
            ['value' => '', 'label' => 'All Status', 'tone' => 'all'],
            ['value' => 'active', 'label' => 'Active', 'tone' => 'active'],
            ['value' => 'inactive', 'label' => 'Inactive', 'tone' => 'deactivated'],
        ];
    }

    public static function domainVerification(string $state): string
    {
        return match ($state) {
            'connected' => 'active',
            'disabled' => 'ban',
            default => 'expiry',
        };
    }

    public static function security(string $variant): string
    {
        return match ($variant) {
            'banned' => 'ban',
            'suspicious' => 'blocked',
            default => 'active',
        };
    }
}
