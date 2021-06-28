<?php

namespace Qmonitor;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Qmonitor\Client\ClientInterface;
use Qmonitor\Client\HttpClient;
use Qmonitor\Commands\QmonitorHeartbeatCommand;
use Qmonitor\Commands\QmonitorSetupCommand;
use Qmonitor\Commands\QmonitorTestJobCommand;
use Qmonitor\EventHandlers\QmonitorEventsSubscriber;

class QmonitorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this
            ->registerCustomQueuePayload()
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

        $this->app->singleton(ClientInterface::class, function ($app) {
            return $app->make(HttpClient::class);
        });
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
            QmonitorSetupCommand::class,
            QmonitorHeartbeatCommand::class,
            QmonitorTestJobCommand::class,
        ]);

        return $this;
    }

    protected function registerEventHandler(): self
    {
        Event::subscribe(QmonitorEventsSubscriber::class);

        return $this;
    }

    protected function registerCustomQueuePayload()
    {
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            return array_merge($payload, [
                'qmonitor_uuid' => Str::uuid(),
            ]);
        });

        return $this;
    }
}
