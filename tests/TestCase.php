<?php

namespace Qmonitor\Tests;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use NunoMaduro\LaravelConsoleTask\LaravelConsoleTaskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Qmonitor\QmonitorServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            QmonitorServiceProvider::class,
            LaravelConsoleTaskServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('qmonitor.app_id', 'abc123');
        $app['config']->set('qmonitor.signing_secret', 'def456');

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function jobEventPayloadMock(object $job): array
    {
        $payload = [
            'uuid' => (string) Str::uuid(),
            'displayName' => get_class($job),
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => optional($job)->tries,
            'maxExceptions' => optional($job)->maxExceptions,
            'backoff' => optional($job)->backoff,
            'timeout' => optional($job)->timeout,
            'retryUntil' => optional($job)->retryUntil,
        ];

        $command = $job instanceof ShouldBeEncrypted && $this->app->bound(Encrypter::class)
                    ? $this->app->make(Encrypter::class)->encrypt(serialize(clone $job))
                    : serialize(clone $job);

        return array_merge($payload, [
            'data' => [
                'commandName' => get_class($job),
                'command' => $command,
            ],
        ]);
    }
}
