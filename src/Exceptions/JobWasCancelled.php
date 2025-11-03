<?php

namespace Mateffy\JobProgress\Exceptions;

use Exception;
use Mateffy\JobProgress\Data\JobState;

class JobWasCancelled extends Exception
{
    public function __construct(public JobState $state)
    {
        parent::__construct(
            message: "Job was cancelled ({$state->job} - {$state->id})"
        );
    }
}
