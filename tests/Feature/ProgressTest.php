<?php

namespace Mateffy\JobProgress\Tests;

use Mateffy\JobProgress\Data\JobState;
use Mateffy\JobProgress\Data\JobStatus;
use Mateffy\JobProgress\Tests\Data\News;
use Mateffy\JobProgress\Tests\Jobs\FailingJob;
use Mateffy\JobProgress\Tests\Jobs\SimpleJob;
use Throwable;

describe("Progress", function () {
    beforeEach(function () {
        News::fake();
    });

    it("can be implemented", function () {
        $news = News::expectRecentProcessing(3);

        $id = uniqid();
        $progress = SimpleJob::getProgress($id, createIfMissing: true);

        expect($progress)
            ->toBeInstanceOf(JobState::class)
            ->and($progress->status)
            ->toBe(JobStatus::Pending)
            ->and($progress->progress)
            ->toBe(0.0);

        SimpleJob::dispatchSync($id);

        $progress = SimpleJob::getProgress($id);

        expect($progress)
            ->toBeInstanceOf(JobState::class)
            ->and($progress->status)
            ->toBe(JobStatus::Completed)
            ->and($progress->progress)
            ->toBe(1.0)
            ->and($progress->result)
            ->toBe("end_result_data");

        expect($news->counter)->toBe(3);
    });

    it("can fail and caught", function () {
        $id = uniqid();

        $error = null;
        try {
            FailingJob::dispatchSync($id);
        } catch (Throwable $e) {
            $error = $e;
        }

        expect($error)
            ->toBeInstanceOf(Throwable::class)
            ->and($error->getMessage())
            ->toBe("test");

        $progress = FailingJob::getProgress($id);

        expect($progress)
            ->toBeInstanceOf(JobState::class)
            ->and($progress->status)
            ->toBe(JobStatus::Failed)
            ->and($progress->progress)
            ->toBe(0.5)
            ->and($progress->result)
            ->toBeNull()
            ->and($progress->error)
            ->toBe($error->getMessage());
    });

    it("doesnt find progress if not exists", function () {
        $id = uniqid();
        $progress = FailingJob::getProgress($id);

        expect($progress)->toBeNull();

        $progress = FailingJob::getProgress($id, createIfMissing: true);

        expect($progress)
            ->toBeInstanceOf(JobState::class)
            ->and($progress->status)
            ->toBe(JobStatus::Pending)
            ->and($progress->progress)
            ->toBe(0.0)
            ->and($progress->result)
            ->toBeNull();
    });
});
