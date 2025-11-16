<?php

namespace Mateffy\JobProgress\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum AverageResolution: string
{
    case PerMinute = "per_minute";
    case PerHour = "per_hour";
    case PerDay = "per_day";
    case PerWeek = "per_week";
    case PerMonth = "per_month";
    case PerYear = "per_year";

    public function calculateResetDate(
        ?CarbonInterface $date = null,
    ): CarbonImmutable {
        $date = ($date ?? CarbonImmutable::now())->toImmutable();

        return match ($this) {
            self::PerMinute => $date->startOfMinute()->addMinute(),
            self::PerHour => $date->startOfHour()->addHour(),
            self::PerDay => $date->startOfDay()->addDay(),
            self::PerWeek => $date->startOfWeek()->addWeek(),
            self::PerMonth => $date->startOfMonth()->addMonth(),
            self::PerYear => $date->startOfYear()->addYear(),
        };
    }
}
