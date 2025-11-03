<?php

namespace Mateffy\JobProgress\Data;

class ProgressSplit
{
    public function __construct(
        public JobState $state,
        public float $base,
        public float $size,
        public bool $completes
    )
    {
    }

    public function complete(mixed $result = null): JobState
    {
        $total = min(100.0, $this->base + $this->size);

        if ($this->completes) {
            $this->state->complete(result: $result);
        } else {
            $this->state->update(progress: $total, result: $result);
        }

        return $this->state;
    }
}
