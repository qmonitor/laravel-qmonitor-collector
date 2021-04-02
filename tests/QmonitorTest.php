<?php

namespace Qmonitor\Tests;

use Qmonitor\Qmonitor;
use Illuminate\Support\Str;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Qmonitor\Support\QmonitorJobPayload;
use Illuminate\Queue\Events\JobProcessing;
use Qmonitor\Support\QmonitorSetupPayload;
use Illuminate\Contracts\Container\Container;
use Qmonitor\Tests\Fixtures\FakePassingTestJob;

class QmonitorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $endpoint = Config::get('qmonitor.endpoint');

        Http::fake([
            "{$endpoint}/*" => Http::response('Ole!', 200),
        ]);
    }

    /** @test */
    public function it_returns_the_collector_version()
    {
        $this->assertNotEmpty(Qmonitor::version());
    }


    /** @test */
    public function it_returns_the_monitored_job_types()
    {
        $this->assertEquals(Qmonitor::monitoredTypes()->toArray(), config('qmonitor.monitor_types'));
    }


    /** @test */
    public function it_returns_the_tags_flag()
    {
        $this->assertEquals(Qmonitor::tagsEnabled(), config('qmonitor.tags'));
    }


    /** @test */
    public function it_returns_the_ping_url()
    {
        $url = sprintf('%s/apps/%s/events', config('qmonitor.endpoint'), config('qmonitor.app_id'));
        $this->assertEquals(Qmonitor::pingUrl(), $url);
    }


    /** @test */
    public function it_returns_the_setup_url()
    {
        $url = sprintf('%s/apps/%s/setup', config('qmonitor.endpoint'), config('qmonitor.app_id'));
        $this->assertEquals(Qmonitor::setupUrl(), $url);
    }


    /** @test */
    public function it_sends_the_ping_payload()
    {
        // Given
        $this->job = new FakePassingTestJob;
        $this->connection = 'sync';

        $payload = $this->jobEventPayloadMock($this->job, $this->connection);

        $this->syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            $this->connection,
            'default'
        );

        $this->event = new JobProcessing($this->connection, $this->syncJob);
        $jobPayload = QmonitorJobPayload::make($this->event);

        // When
        Qmonitor::sendPing($jobPayload->toArray());

        // Then
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request['displayName'] == FakePassingTestJob::class &&
                    $request['event'] == 'processing' &&
                    $request['type'] == 'job';
        });
    }


    /** @test */
    public function it_sends_the_setup_payload()
    {
        // Given
        $setupPayload = [
            'signing_secret' => $secret = Str::random(),
        ];

        // When
        Qmonitor::sendSetup($setupPayload);

        // Then
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($secret) {
            return $request['signing_secret'] == $secret;
        });
    }
}
