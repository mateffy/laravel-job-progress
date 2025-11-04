<?php

namespace Mateffy\JobProgress\Tests\Data;

use Illuminate\Support\Collection;
use Mockery;

class News
{
    public int $counter = 0;


    public function recent(): Collection
    {
        return collect();
    }

    public function process($article): void
    {
        $this->counter++;
    }

    public static function fake(): News & Mockery\MockInterface
    {
        $mock = Mockery::mock(News::class);

        app()->instance(News::class, $mock);

        return $mock;
    }

    public static function expectRecentProcessing(int $times): News & Mockery\MockInterface
    {
        $mock = self::fake();

        $mock
            ->shouldReceive('recent')
            ->once()
            ->andReturn(collect(range(1, $times)));

        $mock
            ->shouldReceive('process')
            ->times($times)
            ->andSet('counter', ...range(1, $times))
            ->andReturnNull();

        return $mock;
    }
}
