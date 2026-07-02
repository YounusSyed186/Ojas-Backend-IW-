<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SubscriptionPeriodService
{
    public function resolve(string $period, CarbonInterface|string $startDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();

        $end = match ($period) {
            'one_day' => $start->copy(),
            'weekly' => $start->copy()->addDays(6),
            'monthly' => $start->copy()->addMonth()->subDay(),
            'quarterly' => $start->copy()->addMonths(3)->subDay(),
            default => throw new InvalidArgumentException('Unsupported subscription period.'),
        };

        return [$start, $end];
    }
}
