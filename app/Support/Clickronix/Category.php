<?php

namespace App\Support\Clickronix;

/**
 * Clickronix scoring categories and caps (manual §4).
 */
final class Category
{
    public const NETWORK = 'network';

    public const BROWSER = 'browser';

    public const DEVICE = 'device';

    public const BEHAVIOR = 'behavior';

    public const FORM = 'form';

    public const CRAWLER = 'crawler';

    public const ACCOUNT = 'account';

    public const HISTORICAL = 'historical';

    /** Points cap per category before final sum. */
    public const CAPS = [
        self::NETWORK => 30,
        self::BROWSER => 30,
        self::DEVICE => 25,
        self::BEHAVIOR => 30,
        self::FORM => 40,
        self::CRAWLER => 40,
        self::ACCOUNT => 40,
        self::HISTORICAL => 30,
    ];

    /**
     * Duplicate automation signals share one browser group cap (Headless + WebDriver).
     */
    public const AUTOMATION_GROUP = 'automation';

    /**
     * @return list<string>
     */
    public static function riskCategories(): array
    {
        return [
            self::NETWORK,
            self::BROWSER,
            self::DEVICE,
            self::BEHAVIOR,
            self::FORM,
            self::CRAWLER,
            self::ACCOUNT,
            self::HISTORICAL,
        ];
    }
}
