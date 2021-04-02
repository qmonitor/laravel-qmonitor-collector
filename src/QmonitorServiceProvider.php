<?php

namespace Qmonitor;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Qmonitor\Commands\QmonitorSetup;
use Qmonitor\Commands\QmonitorTest;
use Qmonitor\EventHandlers\QmonitorEventsSubscriber;

class QmonitorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this
            ->registerPublishables()
            ->registerCommands()
            ->registerEventHandler();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/qmonitor.php', 'qmonitor');
    }

    protected function registerPublishables(): self
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/qmonitor.php' => config_path('qmonitor.php'),
            ], 'config');
        }

        return $this;
    }

    protected function registerCommands(): self
    {
        $this->commands([
            QmonitorSetup::class,
            QmonitorTest::class,
        ]);

        return $this;
    }

    protected function registerEventHandler(): self
    {
        Event::subscribe(QmonitorEventsSubscriber::class);

        return $this;
    }
}
