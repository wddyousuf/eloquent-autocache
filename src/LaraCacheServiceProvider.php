<?php

namespace Hcs\LaraCache;

use Hcs\LaraCache\Console\ClearCommand;
use Hcs\LaraCache\Console\FlushCommand;
use Hcs\LaraCache\Console\StatsCommand;
use Hcs\LaraCache\Console\WarmCommand;
use Illuminate\Support\ServiceProvider;

class LaraCacheServiceProvider extends ServiceProvider
{
    /**
     * Register package services and merge the default configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laracache.php', 'laracache');

        $this->app->singleton('laracache', fn () => new CacheManager);
        $this->app->alias('laracache', CacheManager::class);
    }

    /**
     * Bootstrap package services (publishing config, registering commands).
     */
    public function boot(): void
    {
        $this->registerOctaneListeners();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laracache.php' => $this->app->configPath('laracache.php'),
            ], 'laracache-config');

            $this->commands([
                FlushCommand::class,
                ClearCommand::class,
                WarmCommand::class,
                StatsCommand::class,
            ]);
        }
    }

    /**
     * Reset process-static state at the start of each Octane request/task/tick
     * so a long-lived worker never carries flush state between requests.
     */
    protected function registerOctaneListeners(): void
    {
        // String class names (not ::class) so static analysis doesn't require
        // the optional Octane package to be installed.
        $events = [
            'Laravel\Octane\Events\RequestReceived',
            'Laravel\Octane\Events\TaskReceived',
            'Laravel\Octane\Events\TickReceived',
        ];

        foreach ($events as $event) {
            if (class_exists($event)) {
                $this->app['events']->listen($event, fn () => CacheManager::resetState());
            }
        }
    }
}
