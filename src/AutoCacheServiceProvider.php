<?php

namespace Wddyousuf\AutoCache;

use Illuminate\Support\ServiceProvider;
use Wddyousuf\AutoCache\Console\ClearCommand;
use Wddyousuf\AutoCache\Console\FlushCommand;
use Wddyousuf\AutoCache\Console\StatsCommand;
use Wddyousuf\AutoCache\Console\WarmCommand;

class AutoCacheServiceProvider extends ServiceProvider
{
    /**
     * Register package services and merge the default configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/autocache.php', 'autocache');

        $this->app->singleton('autocache', fn () => new CacheManager);
        $this->app->alias('autocache', CacheManager::class);
    }

    /**
     * Bootstrap package services (publishing config, registering commands).
     */
    public function boot(): void
    {
        $this->registerOctaneListeners();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/autocache.php' => $this->app->configPath('autocache.php'),
            ], 'autocache-config');

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
