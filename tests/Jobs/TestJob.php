<?php

namespace Mateffy\JobProgress\Tests\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;
use Mateffy\JobProgress\Contracts\HasJobProgress;
use Mateffy\JobProgress\Traits\Progress;

/**
 * @method static PendingDispatch dispatch(string $id)
 * @method static PendingDispatch dispatchSync(string $id)
 */
abstract class TestJob implements ShouldQueue, HasJobProgress
{
    use Queueable;
    use Progress;

    public function __construct(
        public string $id,
    )
    {
    }

    public function getProgressId(): string
    {
        return $this->id;
    }
}
