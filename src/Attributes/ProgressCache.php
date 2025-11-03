<?php

namespace Mateffy\JobProgress\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ProgressCache
{
    public function __construct(
        /**
         * The duration in seconds for which the progress state should be cached.
         * @var int|null $duration
         */
        public ?int $duration = null,

        /**
         * The prefix used in the cache key.
         * @var string|null $prefix
         */
        public ?string $prefix = null,

        /**
         * The cache store to use.
         * @var string|null $store
         */
        public ?string $store = null,
    ) {}
}
