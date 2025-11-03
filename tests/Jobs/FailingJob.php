<?php

namespace Mateffy\JobProgress\Tests\Jobs;

class FailingJob extends TestJob
{
    public function handleWithProgress(): void
    {
        // Fake some progress
        $this->progress()->update(0.5);

        throw new \Exception('test');

        $this->progress()->complete(result: 'end_result_data');
    }
}
