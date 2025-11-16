<?php

namespace Mateffy\JobProgress\Traits;

use Exception;
use Mateffy\JobProgress\Attributes\Cancellable;
use Mateffy\JobProgress\Data\JobState;
use Mateffy\JobProgress\Exceptions\JobWasCancelled;
use Mateffy\JobProgress\JobProgressConfig;
use ReflectionClass;
use Throwable;

/**
 * This trait can be used to give jobs the ability to communicate with the rest of the application.
 * It uses the cache to temporarily store the execution status of the job, which can be accesses from the outside
 * to determine the progress or end result of the job.
 *
 * @template T
 */
trait Progress
{
    abstract public function getProgressId(): string;

    /**
     * @throws JobWasCancelled
     * @throws Throwable
     */
    abstract public function handleWithProgress(): void;

    /**
     * We override the handle() method to ensure that the job is correctly marked as processsing, completed or failed.
     * You can still override the handle() method if you want to do something custom, just make sure to mark the
     * progress accordingly and to catch errors, or you will have an inconsistent progress state.
     */
    public function handle(): void
    {
        try {
            // Ensure the job is marked as processing.
            $progress = $this->progress()
                ->exitIfProcessing()
                ->exitIfCancelled()
                ->update(0);

            // Call the actual job logic
            $this->handleWithProgress();

            $progress->refresh();

            // If the job didn't set the completed status itself (for example with some data),
            // we ensure that it is set to completed (even without any result data).
            if ($progress->status->isCompleted() === false) {
                $progress->complete();
            }

            // Track completion durations
            $this->getProgressConfig()->addToAverageDuration(
                job: $this->job,
                duration: $this->duration(),
            );
        } catch (JobWasCancelled $cancelled) {
            $progress = $this->progress();

            // Make sure the job is marked as cancelled, even in case the cancellation was triggered externally
            // (API call returns cancelled etc.) and not just by manually cancelling
            // through ->cancel() on the job progress.
            if ($progress?->status->isCancelled() === false) {
                $progress->progress = $cancelled->progress;
                $progress->result = $cancelled->result;

                $progress->cancel();
            }

            // Afterwards we do nothing and let the job "complete"
        } catch (Throwable $e) {
            report($e);

            $progress = $this->progress();

            // Making sure we catch any errors and mark them
            $progress->fail($e->getMessage());

            $this->onJobError($e);

            // Throw regardless so the normal job error handling can take place.
            // E.g. this enables Nightwatch tracking etc.
            throw $e;
        }
    }

    /**
     * This method can be used to perform actions if a job fails and gets cached by the safe handle method.
     * For example, a user notification can be dispatched here.
     */
    protected function onJobError(Throwable $e): void
    {
        // Do nothing by default.
    }

    public function progress(): JobState
    {
        return self::getProgress(
            id: $this->getProgressId(),
            createIfMissing: true
        );
    }

    /**
     * Get the progress data for the given job instance ID.
     *
     * @param ?string $id The job progress ID (not the job ID from the database!)
     * @param bool $createIfMissing If true, a new pending progress will be created if it doesn't exist yet.'
     * @return JobState|null
     */
    public static function getProgress(?string $id, bool $createIfMissing = false): ?JobState
    {
        // Helper so we can avoid some null checks in code that calls getJobProgress
        if ($id === null) {
            return null;
        }

        $manager = static::getProgressConfig();

        $state = $manager->getJobProgress(
            job: static::class,
            id: $id,
        );

        if (!$state && $createIfMissing) {
            return $manager->createPendingState(
                job: static::class,
                id: $id
            );
        }

        return $state;
    }

    public static function getProgressConfig(): JobProgressConfig
    {
        $params = [];

        // If this class has Cancellable attribute
        $class = new ReflectionClass(static::class);
        $attributes = $class->getAttributes(name: Cancellable::class);

        /** @var ?Cancellable $cancellable */
        $cancellable = ($attributes[0] ?? null)?->newInstance();

        if ($cancellable) {
            $params['cancel_threshold'] = $cancellable->threshold;
        }

        return app(JobProgressConfig::class, $params);
    }

    /**
     * Mark a job with a given ID as pending.
     *
     * Warning: this method will overwrite any existing progress state,
     * and will cause undefined behaviour if a job is currently executing.
     *
     * Make sure to check for currently running jobs before!
     */
    public static function markAsPending(string $id): JobState
    {
        return static::getProgress(id: $id, createIfMissing: true)
            ->reset();
    }

    /**
     * Mark a job with a given ID as pending.
     *
     * Warning: this method will overwrite any existing progress state,
     * and will cause undefined behaviour if a job is currently executing.
     *
     * Make sure to check for currently running jobs before!
     *
     * @return ?JobState The pending job state for this ID, if none exist already.
     */
    public static function lock(string $id): ?JobState
    {
        return static::getProgressConfig()->lock(static::class, id: $id);
    }
}
