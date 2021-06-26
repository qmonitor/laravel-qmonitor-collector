<?php

namespace Qmonitor\Tests\Commands;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Qmonitor\Client\ClientInterface;
use Qmonitor\Jobs\QmonitorHeartbeatJob;
use Qmonitor\Tests\TestCase;
use sixlive\DotenvEditor\DotenvEditor;

class QmonitorHeartbeatCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function it_dispatches_a_heartbeat_job()
    {
        $this->artisan('qmonitor:heartbeat')
            ->expectsOutput('Heartbeat job dispatched!')
            ->assertExitCode(0);

        Queue::assertPushed(QmonitorHeartbeatJob::class);
    }

    /** @test */
    public function it_return_an_error_when_the_qmonitor_is_not_enabled()
    {
        Config::set('qmonitor.enabled', false);

        $this->artisan('qmonitor:heartbeat')
            ->expectsOutput('Qmonitor flag is set to OFF. Check the qmonitor config.')
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_return_an_error_when_the_qmonitor_app_id_is_not_set()
    {
        Config::set('qmonitor.app_id', null);

        $this->artisan('qmonitor:heartbeat')
            ->expectsOutput('Qmonitor app id or signing secret are not set. Make sure you ran the setup command.')
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_return_an_error_when_the_qmonitor_signing_secret_is_not_set()
    {
        Config::set('qmonitor.signing_secret', null);

        $this->artisan('qmonitor:heartbeat')
            ->expectsOutput('Qmonitor app id or signing secret are not set. Make sure you ran the setup command.')
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }
}
