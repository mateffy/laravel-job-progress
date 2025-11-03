<?php

namespace Mateffy\JobProgress\Tests\Data;

use Illuminate\Support\Collection;

class News
{
    public function recent(): Collection
    {
        return collect();
    }

    public function process($article): void
    {

    }
}
