<?php

namespace Mateffy\JobProgress;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Mateffy\JobProgress\Contracts\HasJobProgress;
use Mateffy\JobProgress\Data\JobState;
use Mateffy\JobProgress\Data\JobStatus;
use Throwable;

/**
 * @template R
 */
class JobProgressConfig
{
    public const string DEFAULT_CACHE_PREFIX = "job-progress";
    public const int DEFAULT_CACHE_DURATION = 60 * 15; // 15 minutes
    public const string DEFAULT_CACHE_STORE = "default";
    public const float DEFAULT_CANCEL_THRESHOLD = 0.0;

    public function __construct(
        protected string $cache_store,
        protected string|Closure $cache_prefix,
        protected int $cache_duration,

        /** @var Closure(class-string<HasJobProgress>, string): string $make_cache_key */
        protected ?Closure $make_cache_key,

        protected float $cancel_threshold,
    ) {}

    /**
     * Get the cache store that job progress / status should be stored in.
     */
    public function getCache(): Repository
    {
        return cache()->store($this->cache_store);
    }

    /**
     * How long should the job progress / status be cached for (in seconds)?
     */
    public function getCacheDuration(): int
    {
        return $this->cache_duration;
    }

    /**
     * Get the cache prefix for job progress / status.
     */
    public function getCachePrefix(): string
    {
        $prefix =
            $this->cache_prefix instanceof Closure
                ? ($this->cache_prefix)()
                : $this->cache_prefix;

        return $prefix ?? self::DEFAULT_CACHE_PREFIX;
    }

    /**
     * Returns the maximum percentage before which the job can still be cancelled.
     * This is to prevent cancelling jobs that are already in the process of
     * completing / have reached a point of no return.
     *
     * The check is non-inclusive (<), meaning that if the percentage
     * is exactly the value returned here, it will not be cancellable.
     *
     * For example, if this method returns 75%, then the job can be cancelled
     * at 74.99%, but not at 75%.
     *
     * Cancellation of a job can be disabled by returning 0% her. Returning 100% will enable cancellation at any point.
     * By default, this method returns 0%, meaning that no jobs can be cancelled by default.
     * The default can be overridden by setting the `cancel_threshold` config key to a non-zero value.
     */
    public function getCancelThreshold(): float
    {
        return $this->cancel_threshold;
    }

    /**
     * Returns the unique cache key for the job progress.
     * By default this prefixes the job ID with the job class name,
     * reducing the chance of collisions when using non-random progress IDs (e.g. with a semantic meaning).
     *
     * @param string $id The job progress ID (not the job ID from the database!)
     * @return string
     */
    public function composeCacheKey(string $job, string $id): string
    {
        if ($this->make_cache_key) {
            return app()->call($this->make_cache_key, [
                "job" => $job,
                "id" => $id,
            ]);
        }

        $hash = hash("xxh3", $job);
        $prefix = $this->getCachePrefix();

        return "{$prefix}:{$hash}:{$id}";
    }

    /**
     * Creates and saves a new job state with the given ID and job class.
     *
     * @param class-string<HasJobProgress> $job The job class name
     * @param string $id The job progress ID (not the job ID from the database!)
     * @return JobState
     */
    public function createPendingState(string $job, string $id): JobState
    {
        $progress = new JobState(
            id: $id,
            job: $job,
            status: JobStatus::Pending,
            progress: 0.0,
        );

        $this->saveJobProgress($progress);

        return $progress;
    }

    /**
     * Sets the job progress in the storage (e.g., cache or database).
     *
     * @param JobState $progress
     */
    public function saveJobProgress(JobState $progress): void
    {
        $cacheKey = $this->composeCacheKey(
            job: $progress->job,
            id: $progress->id,
        );

        cache()->put($cacheKey, $progress, ttl: $this->getCacheDuration());
    }

    /**
     * Get the progress data for the given job instance ID.
     *
     * @param class-string<HasJobProgress> $job The job class name
     * @param string $id The job progress ID (not the job ID from the database!)
     * @return JobState<R>|null
     */
    public function getJobProgress(string $job, string $id): ?JobState
    {
        $cacheKey = $this->composeCacheKey(job: $job, id: $id);

        try {
            /** @var ?JobState<R> $progress */
            $progress = cache()->get($cacheKey);

            if ($progress instanceof JobState) {
                return $progress;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * @param class-string<HasJobProgress> $job
     * @param string $id
     * @return bool
     */
    public function canBeCancelled(string $job, string $id): bool
    {
        $state = $this->getJobProgress(job: $job, id: $id);

        return match ($state?->status) {
            default => false,
            null, JobStatus::Pending, JobStatus::Cancelled => true,
            JobStatus::Processing => $state->progress <
                $this->getCancelThreshold(),
        };
    }

    /**
     * Obtain a lock on this job by only returning a new JobState if none with the ID exist already.
     *
     * Useful to only dispatch jobs once safely.
     * This method will also mark the job as pending before it's even executed.
     *
     * @param class-string<HasJobProgress> $job
     * @param string $id
     * @return ?JobState
     */
    public function lock(string $job, string $id): ?JobState
    {
        $state = $this->getJobProgress(job: $job, id: $id);

        if ($state) {
            return null;
        }

        return $this->createPendingState(job: $job, id: $id);
    }
}
