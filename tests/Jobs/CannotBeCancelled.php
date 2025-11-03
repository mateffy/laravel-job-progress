<?php

namespace Mateffy\JobProgress\Tests\Jobs;

class CannotBeCancelled extends TestJob
{
    public function handleWithProgress(): void
    {
        $this->progress()->cancelAndExit();
    }
}
