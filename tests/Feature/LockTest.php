<?php

namespace Mateffy\JobProgress\Tests\Feature;

use Mateffy\JobProgress\Data\JobStatus;
use Mateffy\JobProgress\Tests\Jobs\CancellableJob;
use Mateffy\JobProgress\Tests\Jobs\SimpleJob;

describe("Locking", function () {
    it("should be able to acquire a lock", function () {
        $id = uniqid();

        $progress = SimpleJob::getProgress($id);
        expect($progress)->toBeNull();

        $lock = SimpleJob::lock($id);
        $lock2 = SimpleJob::lock($id);

        expect($lock->status)->toBe(JobStatus::Pending);
        expect($lock2)->toBeNull();

        $progress = SimpleJob::getProgress($id);

        expect($progress->status)->toBe(JobStatus::Pending);
    });

    it(
        "should be able to re-acquire a lock for existing completed, failed and cancelled jobs",
        function () {
            // Completed state
            $completed_id = uniqid();
            $completed = SimpleJob::getProgress(
                $completed_id,
                createIfMissing: true,
            )->complete();

            expect($completed->status)->toBe(JobStatus::Completed);

            $completed_lock = SimpleJob::lock($completed_id);
            expect($completed_lock->status)->toBe(JobStatus::Pending);

            $completed_lock_2 = SimpleJob::lock($completed_id);
            expect($completed_lock_2)->toBeNull();

            // Failed state
            $failed_id = uniqid();
            $failed = SimpleJob::getProgress(
                $failed_id,
                createIfMissing: true,
            )->fail("error");

            expect($failed->status)->toBe(JobStatus::Failed);

            $failed_lock = SimpleJob::lock($failed_id);
            expect($failed_lock->status)->toBe(JobStatus::Pending);

            $failed_lock_2 = SimpleJob::lock($failed_id);
            expect($failed_lock_2)->toBeNull();

            // Cancelled state
            $cancelled_id = uniqid();
            $cancelled = CancellableJob::getProgress(
                $cancelled_id,
                createIfMissing: true,
            )->cancel();

            expect($cancelled->status)->toBe(JobStatus::Cancelled);

            $cancelled_lock = SimpleJob::lock($cancelled_id);
            expect($cancelled_lock->status)->toBe(JobStatus::Pending);

            $cancelled_lock_2 = SimpleJob::lock($cancelled_id);
            expect($cancelled_lock_2)->toBeNull();
        },
    );

    it(
        "should not be able to acquire a lock for pending and processing jobs",
        function () {
            // Pending state
            $pending_id = uniqid();
            $pending = SimpleJob::getProgress(
                $pending_id,
                createIfMissing: true,
            );

            expect($pending->status)->toBe(JobStatus::Pending);

            $pending_lock = SimpleJob::lock($pending_id);
            expect($pending_lock)->toBeNull();

            // Pending state
            $processing_id = uniqid();
            $processing = SimpleJob::getProgress(
                $processing_id,
                createIfMissing: true,
            )->update(0.5);

            expect($processing->status)->toBe(JobStatus::Processing);

            $processing_lock = SimpleJob::lock($processing_id);
            expect($processing_lock)->toBeNull();
        },
    );
});
