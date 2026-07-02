<?php

namespace App\Support;

/**
 * Meal subscription period constants and helper methods
 */
class SubscriptionPeriods
{
    public const DAY = 'day';
    public const WEEK = 'week';
    public const MONTH = 'month';
    public const QUARTERLY = 'quarterly';

    /**
     * All available periods
     */
    public static function all(): array
    {
        return [self::DAY, self::WEEK, self::MONTH, self::QUARTERLY];
    }

    /**
     * Periods that include doctor consultation
     */
    public static function paidPlans(): array
    {
        return [self::WEEK, self::MONTH, self::QUARTERLY];
    }

    /**
     * Periods that do NOT include doctor consultation
     */
    public static function freePeriods(): array
    {
        return [self::DAY];
    }

    /**
     * Check if a period includes doctor consultation
     */
    public static function includesDoctor(string $period): bool
    {
        return in_array($period, self::paidPlans());
    }

    /**
     * Get human-readable period name
     */
    public static function label(string $period): string
    {
        return match ($period) {
            self::DAY => 'Daily',
            self::WEEK => 'Weekly',
            self::MONTH => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            default => $period,
        };
    }
}
