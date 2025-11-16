<?php

namespace Mateffy\JobProgress;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelJobProgressServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name("job-progress")->hasConfigFile()->hasViews();
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(JobProgressConfig::class, function (
            $app,
            array $parameters = [],
        ) {
            return new JobProgressConfig(
                cache_store: $parameters["cache_store"] ??
                    config(
                        "job-progress.cache.store",
                        JobProgressConfig::DEFAULT_CACHE_STORE,
                    ),
                cache_prefix: $parameters["cache_prefix"] ??
                    config(
                        "job-progress.cache.prefix",
                        JobProgressConfig::DEFAULT_CACHE_PREFIX,
                    ),
                cache_duration: $parameters["cache_duration"] ??
                    config(
                        "job-progress.cache.duration",
                        JobProgressConfig::DEFAULT_CACHE_DURATION,
                    ),
                make_cache_key: $parameters["make_cache_key"] ?? null,
                make_global_cache_key: $parameters["make_global_cache_key"] ??
                    null,
                cancel_threshold: $parameters["cancel_threshold"] ??
                    config(
                        "job-progress.cancelling.threshold",
                        JobProgressConfig::DEFAULT_CANCEL_THRESHOLD,
                    ),
                average_resolution: config(
                    "job-progress.average.resolution",
                    null,
                ) ?? null,
            );
        });
    }
}
