<?php

namespace Mateffy\JobProgress\Tests\Feature;

use Mateffy\JobProgress\Data\JobStatus;
use Mateffy\JobProgress\Exceptions\JobCannotBeCancelled;
use Mateffy\JobProgress\Tests\Jobs\CancellableJob;
use Mateffy\JobProgress\Tests\Jobs\FailingJob;
use Mateffy\JobProgress\Tests\Jobs\SimpleJob;

describe('State', function () {
    it('can be created and updated', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);

        expect($progress->status)->toBe(JobStatus::Pending)
            ->and($progress->progress)->toBe(0.0);

        $progress->update(0.5, result: 'abc');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('abc');

        $progress->update(0.75, result: 'abcd');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.75)
            ->and($progress->result)->toBe('abcd');

        $progress->complete(result: 'abcde');

        expect($progress->status)->toBe(JobStatus::Completed)
            ->and($progress->progress)->toBe(1.0)
            ->and($progress->result)->toBe('abcde');
    });

    test('is scoped to job', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);
        $progress2 = FailingJob::getProgress('abc', createIfMissing: true);

        $progress->update(0.5, result: 'dcba');
        $progress2->update(0.2, result: 'abcd');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('dcba')
            ->and($progress2->status)->toBe(JobStatus::Processing)
            ->and($progress2->progress)->toBe(0.2)
            ->and($progress2->result)->toBe('abcd');
    });

    it('can be reduced', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);

        expect($progress->status)->toBe(JobStatus::Pending)
            ->and($progress->progress)->toBe(0.0);

        $progress->update(0.5, result: 'abc');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('abc');

        $progress->update(0.25, result: 'abcd');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.25)
            ->and($progress->result)->toBe('abcd');

        $progress->complete(result: 'abcde');

        expect($progress->status)->toBe(JobStatus::Completed)
            ->and($progress->progress)->toBe(1.0)
            ->and($progress->result)->toBe('abcde');
    });

    it('is limited 0-100%', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);

        expect($progress->status)->toBe(JobStatus::Pending)
            ->and($progress->progress)->toBe(0.0);

        $progress->update(10);

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(1.0);

        $progress->update(-10);

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.0);

        $progress->complete(result: 'abcde');

        expect($progress->status)->toBe(JobStatus::Completed)
            ->and($progress->progress)->toBe(1.0);
    });

    it('can fail', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);

        expect($progress->status)->toBe(JobStatus::Pending)
            ->and($progress->progress)->toBe(0.0);

        $progress->update(0.5, result: 'abc');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('abc');

        $progress->fail('Something went wrong');

        expect($progress->status)->toBe(JobStatus::Failed)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('abc')
            ->and($progress->error)->toBe('Something went wrong');
    });

    it('can be cancelled before processing, even if not configured', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);

        expect($progress->canBeCancelled())->toBeTrue();

        $progress->cancel();
    });

    it('cannot be cancelled after processing, if not configured', function () {
        $progress = SimpleJob::getProgress('abc', createIfMissing: true);

        // Set to processing
        $progress->update(0.5, result: 'abc');

        expect($progress->canBeCancelled())->toBeFalse();

        $progress->cancel();
    })->throws(JobCannotBeCancelled::class);

    it('can be cancelled', function () {
        $progress = CancellableJob::getProgress('abc', createIfMissing: true);

        expect($progress->status)->toBe(JobStatus::Pending)
            ->and($progress->progress)->toBe(0.0);

        $progress->update(0.5, result: 'abc');

        expect($progress->status)->toBe(JobStatus::Processing)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('abc');

        $progress->cancel();

        expect($progress->status)->toBe(JobStatus::Cancelled)
            ->and($progress->progress)->toBe(0.5)
            ->and($progress->result)->toBe('abc')
            ->and($progress->error)->toBeNull();
    });
});

describe('JobStatus', function () {
    test('isPending() helper method', function () {
        expect(JobStatus::Pending->isPending())->toBeTrue()
            ->and(JobStatus::Processing->isPending())->toBeFalse()
            ->and(JobStatus::Completed->isPending())->toBeFalse()
            ->and(JobStatus::Failed->isPending())->toBeFalse()
            ->and(JobStatus::Cancelled->isPending())->toBeFalse();
    });

    test('isRunning() helper method', function () {
        expect(JobStatus::Pending->isRunning())->toBeTrue()
            ->and(JobStatus::Processing->isRunning())->toBeTrue()
            ->and(JobStatus::Completed->isRunning())->toBeFalse()
            ->and(JobStatus::Failed->isRunning())->toBeFalse()
            ->and(JobStatus::Cancelled->isRunning())->toBeFalse();
    });

    test('isProcessing() helper method', function () {
        expect(JobStatus::Pending->isProcessing())->toBeFalse()
            ->and(JobStatus::Processing->isProcessing())->toBeTrue()
            ->and(JobStatus::Completed->isProcessing())->toBeFalse()
            ->and(JobStatus::Failed->isProcessing())->toBeFalse()
            ->and(JobStatus::Cancelled->isProcessing())->toBeFalse();
    });

    test('isFailed() helper method', function () {
        expect(JobStatus::Pending->isFailed())->toBeFalse()
            ->and(JobStatus::Processing->isFailed())->toBeFalse()
            ->and(JobStatus::Completed->isFailed())->toBeFalse()
            ->and(JobStatus::Failed->isFailed())->toBeTrue()
            ->and(JobStatus::Cancelled->isFailed())->toBeFalse();
    });

    test('isCompleted() helper method', function () {
        expect(JobStatus::Pending->isCompleted())->toBeFalse()
            ->and(JobStatus::Processing->isCompleted())->toBeFalse()
            ->and(JobStatus::Completed->isCompleted())->toBeTrue()
            ->and(JobStatus::Failed->isCompleted())->toBeFalse()
            ->and(JobStatus::Cancelled->isCompleted())->toBeFalse();
    });

    test('isFinished() helper method', function () {
        expect(JobStatus::Pending->isFinished())->toBeFalse()
            ->and(JobStatus::Processing->isFinished())->toBeFalse()
            ->and(JobStatus::Completed->isFinished())->toBeTrue()
            ->and(JobStatus::Failed->isFinished())->toBeTrue()
            ->and(JobStatus::Cancelled->isFinished())->toBeTrue();
    });

    test('isCancelled() helper method', function () {
        expect(JobStatus::Pending->isCancelled())->toBeFalse()
            ->and(JobStatus::Processing->isCancelled())->toBeFalse()
            ->and(JobStatus::Completed->isCancelled())->toBeFalse()
            ->and(JobStatus::Failed->isCancelled())->toBeFalse()
            ->and(JobStatus::Cancelled->isCancelled())->toBeTrue();
    });
});
