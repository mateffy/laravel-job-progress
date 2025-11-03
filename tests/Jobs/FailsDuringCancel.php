<?php

namespace Mateffy\JobProgress\Tests\Jobs;

use Mateffy\JobProgress\Attributes\Cancellable;

#[Cancellable(threshold: 0.5)]
class FailsDuringCancel extends TestJob
{

    public function handleWithProgress(): void
    {
        // Update progress past the threshold
        $this->progress()->update(0.6);

        // Attempt to cancel the job (should fail)
        $this->progress()->cancelAndExit();
    }
}
