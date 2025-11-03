<?php

namespace Mateffy\JobProgress\Exceptions;

use Exception;

class JobCannotBeCancelled extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            message: $message ?? 'The job cannot be cancelled (any more)',
        );
    }
}
