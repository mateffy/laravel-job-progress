<?php

namespace Mateffy\JobProgress\Support;

class Math
{
    /**
     * Simple helper for min-maxing a number.
     */
    public static function clamp(float|int $value, float|int $min, float|int $max): float|int
    {
        return min(max($value, $min), $max);
    }
}
