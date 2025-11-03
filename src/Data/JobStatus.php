<?php

namespace Mateffy\JobProgress\Data;

enum JobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Determine if the job has been running. Pending is counted as running
     * as the job will be started eventually. Use isProcessing() if you want to
     * check if the job is actually doing any work right now.
     */
    public function isRunning(): bool
    {
        return match ($this) {
            self::Pending, self::Processing => true,
            default => false,
        };
    }

    /**
     * Has the job been queued but not yet started?
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::Pending => true,
            default => false,
        };
    }

    /**
     * Is the job currently running in a worker?
     */
    public function isProcessing(): bool
    {
        return match ($this) {
            self::Processing => true,
            default => false,
        };
    }

    /**
     * Has the job been run and finished executing, either with an error or with failure?
     */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Has the job been completed successfully?
     */
    public function isCompleted(): bool
    {
        return match ($this) {
            self::Completed => true,
            default => false,
        };
    }

    /**
     * Has the job finished executing with an error?
     */
    public function isFailed(): bool
    {
        return match ($this) {
            self::Failed => true,
            default => false,
        };
    }

    /**
     * Has the job been manually cancelled?
     */
    public function isCancelled(): bool
    {
        return match ($this) {
            self::Cancelled => true,
            default => false,
        };
    }
}
