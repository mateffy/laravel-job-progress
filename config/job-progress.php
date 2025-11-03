<?php

// config for Mateffy/LaravelJobProgress
return [
    'cache' => [
        'store' => \Mateffy\JobProgress\JobProgressConfig::DEFAULT_CACHE_STORE,
        'prefix' => \Mateffy\JobProgress\JobProgressConfig::DEFAULT_CACHE_PREFIX,
        'duration_seconds' => \Mateffy\JobProgress\JobProgressConfig::DEFAULT_CACHE_DURATION,
    ],
    'cancelling' => [
        'threshold' => \Mateffy\JobProgress\JobProgressConfig::DEFAULT_CANCEL_THRESHOLD,
    ]
];
