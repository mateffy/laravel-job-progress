<?php

namespace Mateffy\JobProgress\Exceptions;

use Exception;
use Mateffy\JobProgress\Data\JobState;

class JobAlreadyProcessing extends Exception
{
    public function __construct(public JobState $state)
    {
        parent::__construct(
            message: "Job {$state->job} with ID {$state->id} is already processing",
        );
    }
}
