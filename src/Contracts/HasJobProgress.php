<?php

namespace Mateffy\JobProgress\Contracts;

use Mateffy\JobProgress\Data\JobState;
use Mateffy\JobProgress\JobProgressConfig;

/**
 * @template T
 */
interface HasJobProgress
{
    /**
     * Progress
     */
    public function handleWithProgress(): void;

    /**
     * Return the progress ID of the currently runnning job.
     * The source of the ID may differ based on how the job itself is built,
     * but best-practice is simply passing a known, unique ID along as an input argument in the constructor.
     *
     * @return string A unique ID for the job progress.
     */
    public function getProgressId(): string;

    /**
     * Returns the job progress state for the current job with the ID returned by `getJobId`.
     */
    public function progress(): JobState;

    /**
     * Returns the job progress from the storage (e.g., cache or database).
     * Optionally, a new pending progress can be created if it doesn't exist yet by using `createIfMissing`.
     *
     * @param string|null $id The job progress ID (not the job ID from the database!)
     * @param bool $createIfMissing If true, a new pending progress will be created if it doesn't exist yet.'
     * @return JobState<T>|null
     */
    public static function getProgress(?string $id, bool $createIfMissing = false): ?JobState;

    public static function getProgressConfig(): JobProgressConfig;
}
