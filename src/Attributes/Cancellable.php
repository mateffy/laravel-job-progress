<?php

namespace Mateffy\JobProgress\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Cancellable
{
    public function __construct(
        public float $threshold = 1.0,
    ) {
    }
}
