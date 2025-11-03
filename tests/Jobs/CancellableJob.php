<?php

namespace Mateffy\JobProgress\Tests\Jobs;

use Mateffy\JobProgress\Attributes\Cancellable;

#[Cancellable]
class CancellableJob extends TestJob
{

    public function handleWithProgress(): void
    {
        $this->progress()->update(0.5);

        $this->progress()->cancelAndExit();
    }
}
