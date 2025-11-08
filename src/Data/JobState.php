<?php

namespace Mateffy\JobProgress\Data;

use Closure;
use Exception;
use Mateffy\JobProgress\Contracts\HasJobProgress;
use Mateffy\JobProgress\Exceptions\JobAlreadyProcessing;
use Mateffy\JobProgress\Exceptions\JobCannotBeCancelled;
use Mateffy\JobProgress\Exceptions\JobWasCancelled;
use Mateffy\JobProgress\JobProgressConfig;
use Mateffy\JobProgress\Support\Math;

/**
 * @template T
 */
class JobState
{
    public function __construct(
        public string $id,
        /** @var class-string<HasJobProgress> $job */
        public string $job,
        public JobStatus $status,
        public float $progress,
        public ?string $error = null,

        /** @var T $result */
        public $result = null,
        protected ?JobProgressConfig $config = null,
    ) {}

    public function getProgressConfig(): JobProgressConfig
    {
        if ($this->config === null) {
            $this->config = $this->job::getProgressConfig();
        }

        return $this->config;
    }

    /**
     * Refreshes the progress from the database and updates this current JobProgress instance (not immutable).
     * @return JobState<T>
     */
    public function refresh(): self
    {
        $refreshed = $this->getProgressConfig()->getJobProgress(
            job: $this->job,
            id: $this->id,
        );

        if ($refreshed) {
            $this->status = $refreshed->status;
            $this->progress = $refreshed->progress;
            $this->result = $refreshed->result;
            $this->error = $refreshed->error;
        }

        return $this;
    }

    /**
     * Update the progress of the job.
     * If the job is still marked as pending, it will be set to processing.
     * However, we allow updating the progress for all other statuses without changing the status back to processing.
     *
     * @param float $progress The new absolute progress value (between 0 and 1)
     * @param mixed|null $result A (partial) result associated with this progress update.
     * @return $this The mutated JobState (same instance, not cloned)
     */
    public function update(float $progress, mixed $result = null): self
    {
        $this->refresh();

        // If the job is pending, we need to set it to processing if we're updating the progress.
        // But: we allow updating the progress for completed / failed jobs without changing the status.
        if ($this->status->isPending()) {
            $this->status = JobStatus::Processing;
        }

        $this->progress = Math::clamp($progress, min: 0, max: 1.0);
        $this->result = $result ?? $this->result;

        $this->getProgressConfig()->saveJobProgress($this);

        return $this;
    }

    /**
     * Calculate and update a relative progress based on completed and total steps.
     * Useful inside for or while loops to update progress as items get processed.
     *
     *
     * @param int $completed
     * @param int $total
     * @param mixed|null $result
     * @param float|null $max
     * @param float|null $base
     * @return $this
     */
    public function updateWithSteps(
        int $completed,
        int $total,
        mixed $result = null,
        float|null $max = null,
        float|null $base = null,
    ): self {
        // Make sure the values work correctly.
        // By clamping the maximum to 100% - the base, we make sure that the maximum is never exceeded.
        $baseFloat = Math::clamp($base ?? 0.0, min: 0, max: 100.0);
        $maxFloat = Math::clamp($max ?? 100.0, min: 0, max: 100.0 - $baseFloat);

        // If the total is smaller than the current value, we set the total to the current value
        // to avoid percentages larger than 100%
        $total = max($completed, $total);

        // Calculate the new progress.
        // Make sure we don't divide by 0.
        $fraction = $total === 0 ? 0.0 : $completed / $total;

        // Calculate the new total by adding the base and multiplying the max possible value by the fraction
        $total = $baseFloat + $maxFloat * $fraction;

        // Make sure the total is within the range of 0-100.
        // Handle using fallback value
        if ($total > 100.0) {
            // Report the issue so it can be investiagated by devs
            report(new Exception("Total progress exceeded 100%: {$total}"));

            // We don't want to crash the job here either, so we just clamp the total to 100%
            // Again, UI may look broken, but we did report the issue and don't crash for a UI issue.
            $total = 100.0;
        }

        return $this->update(progress: $total, result: $result);
    }

    /**
     * Mark the job as completed. Accepts an optional result value, which will also be stored in the cache.
     *
     * Since the result is also stored, only use when necessary with the data you actually need.
     * Cache uses PHP object serialization, meaning you can pass DTOs or other classes.
     *
     * @param mixed|null $result
     * @return $this
     */
    public function complete(mixed $result = null): self
    {
        $this->refresh();

        $this->status = JobStatus::Completed;
        $this->progress = 1.0;
        $this->result = $result ?? $this->result;

        $this->getProgressConfig()->saveJobProgress($this);

        return $this;
    }

    /**
     * Mark the job as failed.
     * In most cases you won't want to do this manually, as the `Progress` trait does this automatically.
     * Marking as failed does not have an effect on execution flow.
     *
     * @param string $error
     * @return $this
     */
    public function fail(string $error): self
    {
        $this->refresh();

        $this->error = $error;
        $this->status = JobStatus::Failed;

        $this->getProgressConfig()->saveJobProgress($this);

        return $this;
    }

    /**
     * Set the status back to pending and reset progress, result and error values of this state.
     *
     * Useful to ensure the job state is marked as pending.
     * Note that this method will NOT check if the job is currently running
     * and will mindlessly override.
     *
     * Make sure no job is currently executing or this will cause undefined behavior.
     */
    public function reset(): self
    {
        $this->refresh();

        $this->error = null;
        $this->progress = 0.0;
        $this->result = null;
        $this->status = JobStatus::Pending;

        $this->getProgressConfig()->saveJobProgress($this);

        return $this;
    }

    /**
     * Tries to cancel the job by marking it as cancelled and saving the progress.
     * This method DOES NOT throw JobWasCancelled to stop any current execution, so it's safe to use outside jobs.
     *
     * This can be used inside the job itself to check between long-running tasks to see if the job should be cancelled.
     * There are no guarantees that the job will be cancelled, as that depends on the job itself
     * and if the cancellation was even invoked in time.
     *
     * If the job cannot be cancelled (any more), this method will throw JobCannotBeCancelled.
     *
     * @throws JobCannotBeCancelled
     */
    public function cancel(): self
    {
        if (!$this->canBeCancelled()) {
            throw new JobCannotBeCancelled(state: $this);
        }

        $this->refresh();

        $this->status = JobStatus::Cancelled;

        $this->getProgressConfig()->saveJobProgress($this);

        return $this;
    }

    /**
     * Tries to cancel the job by marking it as cancelled and throwing JobWasCancelled to stop any current job execution.
     * This method should ONLY be used inside jobs, or the JobWasCancelled exception must be handled manually.
     *
     * This can be used inside the job itself to check between long-running tasks to see if the job should be cancelled.
     * There are no guarantees that the job will be cancelled, as that depends on the job itself
     * and if the cancellation was even invoked in time.
     *
     * If the job cannot be cancelled (any more), this method will throw JobCannotBeCancelled.
     *
     * @throws JobWasCancelled
     * @throws JobCannotBeCancelled
     */
    public function cancelAndExit(): void
    {
        $this->cancel();

        throw new JobWasCancelled(state: $this);
    }

    /**
     * Check if the job was cancelled and throw JobWasCancelled if it was.
     * Can be used for creating "checkpoints" inside jobs, which stop execution at a given line if the job was cancelled in the meantime.
     *
     * @param Closure|null $cleanup A closure that executes if the job was cancelled. Useful for cleaning up resources.
     *
     * @throws JobWasCancelled
     */
    public function exitIfCancelled(?Closure $cleanup = null): self
    {
        if (!$this->status->isCancelled()) {
            return $this;
        }

        // Run cleanup if provided
        if ($cleanup) {
            $cleanup();
        }

        throw new JobWasCancelled(state: $this);
    }

    /**
     * Check if the job is already processing and throw a JobAlreadyProcessing if it is.
     * Is used to validate that a job is not already processing at the beginning of the handler.
     *
     * @param Closure|null $cleanup A closure that executes if the job is already processing. Useful for cleaning up resources.
     *
     * @throws JobAlreadyProcessing
     */
    public function exitIfProcessing(?Closure $cleanup = null): self
    {
        if (!$this->status->isProcessing()) {
            return $this;
        }

        // Run cleanup if provided
        if ($cleanup) {
            $cleanup();
        }

        throw new JobAlreadyProcessing(state: $this);
    }

    /**
     * Check if the job can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->getProgressConfig()->canBeCancelled(
            job: $this->job,
            id: $this->id,
        );
    }

    public function split(array|int $steps): array
    {
        if (is_int($steps)) {
            $steps = max(1, $steps);
            $size = 100.0 / $steps;

            $splits = [];
            $total = 0.0;

            for ($i = 0; $i < $steps; $i++) {
                $isLast = $i === $steps - 1;

                // Try to avoid rounding errors as best as possible
                // by calculating the rest needed until 100.0.
                // If $size has precision errors (too large / too small)
                // this will simply adjust the last split by a tiny amount.
                $safeSize = $isLast ? $size : 100.0 - $total;

                $splits[] = new ProgressSplit(
                    state: $this,
                    base: $total,
                    size: $safeSize,
                    completes: $isLast,
                );
            }
        } else {
            $count = count($steps);

            // If your custom split sizes don't add up to 100% (more / less)
            // we recalculate the total to use instead, making your splits relative
            // instead of absolute.
            // Example: [0.25, 0.5, 0.25] = 100% -> [0.25, 0.5, 0.25] -> 100%
            // Example: [0.25, 0.5, 0.5] = 125% -> [0.20, 0.4, 0.4] -> 100%
            $total = collect($steps)->sum();

            // If the steps sum to 0, something is wrong,
            // we fix by just using the same number of steps but using fixed sizes.
            if ($total === 0) {
                $size = 100.0 / $count;

                $steps = collect($steps)->map(fn(int $step) => $size)->all();

                $total = 1.0;
            }

            $fraction = $total / 100.0;

            $splits = [];

            foreach ($steps as $i => $step) {
                $isLast = $i === $count - 1;

                // Calculate the sa
                $safeSize = $step / $fraction;

                $splits[] = new ProgressSplit(
                    state: $this,
                    base: $total,
                    size: $safeSize,
                    completes: $isLast,
                );
            }
        }

        return $splits;
    }
}
