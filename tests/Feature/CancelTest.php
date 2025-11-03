<?php

namespace Mateffy\JobProgress\Tests\Feature;

use Mateffy\JobProgress\Data\JobStatus;
use Mateffy\JobProgress\Exceptions\JobCannotBeCancelled;
use Mateffy\JobProgress\Tests\Jobs\CancelledBeforeExecution;
use Mateffy\JobProgress\Tests\Jobs\CannotBeCancelled;
use Mateffy\JobProgress\Tests\Jobs\FailsDuringCancel;
use Mateffy\JobProgress\Tests\Jobs\CancellableJob;

describe('Cancellation', function () {
    it('can be cancelled', function () {
        $id = uniqid();
        CancellableJob::dispatchSync($id);

        $progress = CancellableJob::getProgress($id);

        expect($progress->status)->toBe(JobStatus::Cancelled)
            ->and($progress->progress)->toBe(0.5);
    });

    it('cannot be cancelled without attribute', function () {
        $id = uniqid();
        CannotBeCancelled::dispatchSync($id);
    })->throws(JobCannotBeCancelled::class);

    it('CAN be cancelled ahead of time without attribute', function () {
        $id = uniqid();
        $progress = CannotBeCancelled::getProgress($id, createIfMissing: true);

        $progress->cancel();
        $progress->refresh();

        expect($progress->status)->toBe(JobStatus::Cancelled);
    });

    it('cannot be cancelled after/on threshold', function () {
        $id = uniqid();

        $error = null;

        try {
            FailsDuringCancel::dispatchSync($id);
        } catch (\Throwable $e) {
            $error = $e;
        }

        expect($error)->toBeInstanceOf(JobCannotBeCancelled::class);

        $progress = FailsDuringCancel::getProgress($id);

        // Normally, an attempt to cancel a job would not result in a failure. But the cancellation happens
        // INSIDE the job, so it will fail.
        expect($progress->status)->toBe(JobStatus::Failed)
            ->and($progress->progress)->toBe(0.6);
    });

    it('can be cancelled before execution', function () {
        $id = uniqid();
        $progress = CancelledBeforeExecution::getProgress($id, createIfMissing: true);
        $progress->cancel();

        CancelledBeforeExecution::dispatchSync($id);

        $progress = CancelledBeforeExecution::getProgress($id);

        expect($progress->status)->toBe(JobStatus::Cancelled)
            ->and($progress->progress)->toBe(0.0);
    });
});
