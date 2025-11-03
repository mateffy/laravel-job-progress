<?php

namespace Mateffy\JobProgress\Tests\Jobs;

use Exception;
use Mateffy\JobProgress\Attributes\Cancellable;

#[Cancellable]
class CancelledBeforeExecution extends TestJob
{
    public function handleWithProgress(): void
    {
        $this->progress()->update(0.5);

        throw new Exception('this should never been thrown as this job was cancelled before execution');
    }
}
