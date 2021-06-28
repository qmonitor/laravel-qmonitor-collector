<?php

namespace Qmonitor\Tests;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Str;
use NunoMaduro\LaravelConsoleTask\LaravelConsoleTaskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Qmonitor\QmonitorServiceProvider;
use Zttp\Zttp;
use Zttp\ZttpResponse;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        $this->httpMock = $this->mock(Zttp::class, function ($mock) {
            $mock->allows([
                'asJson' => $mock,
                'accept' => $mock,
                'timeout' => $mock,
                'withHeaders' => $mock,
                'isClientError' => false,
                'isServerError' => false,
            ]);
        });
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
            'qmonitor_uuid' => (string) Str::uuid(),
            'displayName' => get_class($job),
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => optional($job)->tries,
            'maxExceptions' => optional($job)->maxExceptions,
            'backoff' => optional($job)->backoff,
            'timeout' => optional($job)->timeout,
            'retryUntil' => optional($job)->retryUntil,
        ];

        $command = serialize(clone $job);

        return array_merge($payload, [
            'data' => [
                'commandName' => get_class($job),
                'command' => $command,
            ],
        ]);
    }

    public function buildFakeResponse(array $payload = [], int $status = 200, array $headers = [])
    {
        return new ZttpResponse(
            new GuzzleResponse(
                $status,
                $headers,
                json_encode($payload)
            )
        );
    }
}
