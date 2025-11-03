<?php

namespace Mateffy\JobProgress\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mateffy\JobProgress\Tests\Data\News;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Mateffy\JobProgress\LaravelJobProgressServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Mateffy\\JobProgress\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelJobProgressServiceProvider::class,
        ];
    }

    public function mockNews(): News&Mockery\MockInterface
    {
        $mock = Mockery::mock(News::class);

        $this->instance(News::class, $mock);

        return $mock;
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}
