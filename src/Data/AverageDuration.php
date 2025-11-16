<?php

namespace Mateffy\JobProgress\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use InvalidArgumentException;

class AverageDuration
{
    public function __construct(
        protected AverageResolution $resolution,
        protected CarbonImmutable $reset_at,
        /** @var int[] $durations */
        protected array $durations = [],
        protected ?int $carry_average = null,
        protected int $carry_threshold = 20,
        protected float $carry_weight = 5,
    ) {
        if (\count($durations) === 0) {
            throw new InvalidArgumentException(
                "At least 1 duration needs to be passed",
            );
        }
    }

    public function interval(): CarbonInterval
    {
        $num_durations = max(1, \count($this->durations));
        $duration_sum = array_sum($this->durations);

        $weighted_carry = ($this->carry_average ?? 0.0) * $this->carry_weight;

        $divider =
            $num_durations + ($this->carry_average ? $this->carry_weight : 0);

        $avg_ms = round(($duration_sum + $weighted_carry) / $divider);

        return CarbonInterval::milliseconds($avg_ms);
    }

    public function add(CarbonInterval $duration): self
    {
        $num_durations = max(1, \count($this->durations));

        if ($num_durations >= $this->carry_threshold) {
            $this->carry_average = round(
                array_sum($this->durations) / $num_durations,
            );
            $this->durations = [];
        }

        if ($this->reset_at->isPast()) {
            $this->carry_average = null;
            $this->durations = [];
            $this->reset_at = $this->resolution->calculateResetDate();
        }

        $this->durations[] = (int) $duration->roundMilliseconds()
            ->totalMilliseconds;

        return $this;
    }

    public function getCarryAverage(): ?int
    {
        return $this->carry_average;
    }

    public static function new(
        AverageResolution $resolution,
        CarbonInterval $duration,
    ): self {
        // $resolution =
        //     "per_minute" ??
        //     ("per_hour" ?? ("per_day" ?? ("per_month" ?? "per_year")));

        return new AverageDuration(
            resolution: $resolution,
            reset_at: $resolution->calculateResetDate(),
            durations: [
                (int) $duration->roundMicroseconds()->totalMilliseconds,
            ],
        );
    }
}
