<?php

namespace Qmonitor\Tests;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Qmonitor\Jobs\QmonitorHeartbeatJob;
use Qmonitor\Jobs\QmonitorPingJob;
use Qmonitor\Qmonitor;
use Qmonitor\Support\QmonitorJobPayload;
use Qmonitor\Tests\Fixtures\FakeEncryptedJob;
use Qmonitor\Tests\Fixtures\FakePassingTestJob;

class QmonitorEventsSubscriberTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Http::fake([
        //     'https://fail.qmonitor.io/*' => Http::response(['message' => 'Fail!'], 400),
        //     "*" => Http::response(['message' => 'Ole!'], 200),
        // ]);

        $this->job = new FakePassingTestJob;

        $payload = $this->jobEventPayloadMock($this->job, 'sync');

        $this->syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            'sync',
            'default'
        );
    }

    /** @test */
    public function it_listens_for_job_processing_event()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $this->httpMock
            ->shouldReceive('post')
            ->once()
            ->withArgs(function ($url, $payload) {
                return $url === Qmonitor::pingUrl() &&
                    $payload['displayName'] === FakePassingTestJob::class &&
                    $payload['event'] === 'processing' &&
                    $payload['type'] === 'job';
            })
            ->andReturn($this->buildFakeResponse());

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_listens_for_job_processed_event()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $this->httpMock
            ->shouldReceive('post')
            ->once()
            ->withArgs(function ($url, $payload) {
                return $url === Qmonitor::pingUrl() &&
                    $payload['displayName'] === FakePassingTestJob::class &&
                    $payload['event'] === 'processed' &&
                    $payload['type'] === 'job';
            })
            ->andReturn($this->buildFakeResponse());

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessed('sync', $this->syncJob));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_listens_for_job_failed_event()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $exception = new Exception($message = 'This job has failed');

        $this->httpMock
            ->shouldReceive('post')
            ->once()
            ->withArgs(function ($url, $payload) {
                return $url === Qmonitor::pingUrl() &&
                    $payload['displayName'] === FakePassingTestJob::class &&
                    $payload['event'] === 'failed' &&
                    $payload['type'] === 'job';
            })
            ->andReturn($this->buildFakeResponse());

        tap($this->app->make(Dispatcher::class), function ($dispatcher) use ($exception) {
            $dispatcher->dispatch(new JobFailed('sync', $this->syncJob, $exception));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_checks_for_monitor_enabled_flag()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        Config::set([
            'qmonitor.enabled' => false,
        ]);

        $this->httpMock->shouldNotReceive('post');

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_checks_for_qmonitor_app_id()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        Config::set([
            'qmonitor.app_id' => null,
        ]);

        $this->httpMock->shouldNotReceive('post');

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_respects_the_monitored_type_config()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        Config::set([
            'qmonitor.monitor_types.job' => false,
        ]);

        $this->assertFalse(Config::get('qmonitor.monitor_types.job'));

        $this->httpMock->shouldNotReceive('post');

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_ignores_qmonitor_ping_jobs()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $jobPayload = QmonitorJobPayload::make(new JobProcessing('sync', $this->syncJob));

        $job = new QmonitorPingJob($jobPayload->toArray());

        $payload = $this->jobEventPayloadMock($job, 'sync');

        $syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            'sync',
            'default'
        );

        $this->httpMock->shouldNotReceive('post');

        tap($this->app->make(Dispatcher::class), function ($dispatcher) use ($syncJob) {
            $dispatcher->dispatch(new JobProcessing('sync', $syncJob));
        });

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_dispatches_a_job_if_an_error_is_thrown()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $this->httpMock
            ->shouldReceive('post')
            ->once()
            ->andReturn($this->buildFakeResponse(['message' => 'Fail!'], 400));

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Queue::assertPushed(QmonitorPingJob::class);
    }

    /** @test */
    public function it_ignores_qmonitor_heartbeat_jobs()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $job = new QmonitorHeartbeatJob();

        $payload = $this->jobEventPayloadMock($job, 'sync');

        $syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            'sync',
            'default'
        );

        $this->httpMock->shouldNotReceive('post');

        tap($this->app->make(Dispatcher::class), function ($dispatcher) use ($syncJob) {
            $dispatcher->dispatch(new JobProcessing('sync', $syncJob));
        });

        Queue::assertNothingPushed();
    }
}
