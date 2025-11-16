<?php

namespace Mateffy\JobProgress\Tests\Feature;

use Carbon\CarbonInterval;
use Mateffy\JobProgress\Data\AverageDuration;
use Mateffy\JobProgress\Data\AverageResolution;
use Mateffy\JobProgress\Tests\Jobs\SimpleJob;

describe("Average Calculation", function () {
    it("can calculate simple averages", function () {
        $average = new AverageDuration(
            resolution: AverageResolution::PerDay,
            reset_at: AverageResolution::PerDay->calculateResetDate(),
            durations: [1000, 2000, 3000, 4000],
        );

        // 2500 = (1000 + 2000 + 3000 + 4000) / 4;

        expect($average->interval()->milliseconds)->toEqual(2500);
    });

    it("can calculate simple averages with carry", function () {
        $average = new AverageDuration(
            resolution: AverageResolution::PerDay,
            reset_at: AverageResolution::PerDay->calculateResetDate(),
            durations: [1000, 2000, 3000, 4000],
            carry_average: 1000,
            carry_weight: 5,
        );

        // 1666.6666666666 = ((1000 + 2000 + 3000 + 4000) + (1000 * 5)) / (4 + 5);
        // but it's rounded to 1667

        expect($average->interval()->milliseconds)->toEqual(1667);

        $average2 = new AverageDuration(
            resolution: AverageResolution::PerDay,
            reset_at: AverageResolution::PerDay->calculateResetDate(),
            durations: [1000, 2000, 3000, 4000],
            carry_average: 1000,
            carry_weight: 10,
        );

        // 1428.57 = ((1000 + 2000 + 3000 + 4000) + (1000 * 10)) / (4 + 10);
        // but it's rounded to 1429

        expect($average2->interval()->milliseconds)->toEqual(1429);
    });

    it("can add to average", function () {
        $average = new AverageDuration(
            resolution: AverageResolution::PerDay,
            reset_at: AverageResolution::PerDay->calculateResetDate(),
            durations: [1000, 2000, 3000, 4000],
            carry_average: 1000,
            carry_weight: 5,
        );

        // 1666.6666666666 = ((1000 + 2000 + 3000 + 4000) + (1000 * 5)) / (4 + 5);
        // but it's rounded to 1667

        $average->add(CarbonInterval::milliseconds(5000));

        // after adding:
        // 2000 = ((1000 + 2000 + 3000 + 4000 + 5000) + (1000 * 5)) / (5 + 5);

        expect($average->interval()->milliseconds)->toEqual(2000);
    });

    it("can add to average and set carry", function () {
        $average = new AverageDuration(
            resolution: AverageResolution::PerDay,
            reset_at: AverageResolution::PerDay->calculateResetDate(),
            durations: [1000, 2000, 3000, 4000, 5003],
            carry_average: null,
            carry_threshold: 5,
            carry_weight: 2,
        );

        // 3000.6 = (1000 + 2000 + 3000 + 4000 + 5003) / 5;
        // rounded to 3001

        expect($average->interval()->milliseconds)->toEqual(3001);

        $average->add(CarbonInterval::milliseconds(5000));

        expect($average->getCarryAverage())->toEqual(3001);

        // 3667.3 = ((5000) + (3001 * 2)) / (1 + 2)
        // rounded to 3667

        expect($average->interval()->milliseconds)->toEqual(3667);
    });

    it("can add to average and resets values after date", function () {
        $average = new AverageDuration(
            resolution: AverageResolution::PerDay,
            reset_at: AverageResolution::PerDay
                ->calculateResetDate()
                ->subDays(2),
            durations: [1000, 2000, 3000, 4000, 5003],
            carry_average: 2000,
            carry_threshold: 6,
            carry_weight: 2,
        );

        // 2714.7 = ((1000 + 2000 + 3000 + 4000 + 5003) + (2000 * 2)) / (5 + 2);
        // rounded to 2715

        expect($average->interval()->milliseconds)->toEqual(2715);

        // should reset after adding since the date is up
        $average->add(CarbonInterval::milliseconds(5000));

        expect($average->getCarryAverage())->toBeNull();
        expect($average->interval()->milliseconds)->toEqual(5000);
    });

    it("doesn't collect by default", function () {
        $config = SimpleJob::getProgressConfig();

        $config->addToAverageDuration(
            SimpleJob::class,
            CarbonInterval::seconds(20),
        );

        $duration = $config->getAverageDuration(SimpleJob::class);

        expect($duration)->toBeNull();
    });

    it("can set the average for a job", function () {
        $id = uniqid();

        // Make sure we're even collecting resolution
        config()->set(
            "job-progress.average.resolution",
            AverageResolution::PerWeek,
        );

        $config = SimpleJob::getProgressConfig();

        $config->addToAverageDuration(
            SimpleJob::class,
            CarbonInterval::seconds(20),
        );

        $config->addToAverageDuration(
            SimpleJob::class,
            CarbonInterval::seconds(10),
        );

        $config->addToAverageDuration(
            SimpleJob::class,
            CarbonInterval::seconds(20),
        );

        $duration = $config->getAverageDuration(SimpleJob::class);

        // 16666.6 = (20000 + 10000 + 20000) / 3
        // -> 16667

        expect($duration->milliseconds)->toEqual(16667);
    });
});
