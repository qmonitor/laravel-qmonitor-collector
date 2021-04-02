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
use Qmonitor\Jobs\QmonitorPingJob;
use Qmonitor\Support\QmonitorJobPayload;
use Qmonitor\Tests\Fixtures\FakeEncryptedJob;
use Qmonitor\Tests\Fixtures\FakePassingTestJob;

class QmonitorEventsSubscriberTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $endpoint = Config::get('qmonitor.endpoint');

        Http::fake([
            "{$endpoint}/*" => Http::response(['message' => 'Ole!'], 200),
        ]);

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

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request['displayName'] == FakePassingTestJob::class &&
                    $request['event'] == 'processing' &&
                    $request['type'] == 'job';
        });
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_listens_for_job_processed_event()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessed('sync', $this->syncJob));
        });

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request['displayName'] == FakePassingTestJob::class &&
                    $request['event'] == 'processed' &&
                    $request['type'] == 'job';
        });
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_listens_for_job_failed_event()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $exception = new Exception($message = 'This job has failed');

        tap($this->app->make(Dispatcher::class), function ($dispatcher) use ($exception) {
            $dispatcher->dispatch(new JobFailed('sync', $this->syncJob, $exception));
        });

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($message) {
            return $request['displayName'] == FakePassingTestJob::class &&
                    $request['event'] == 'failed' &&
                    $request['type'] == 'job' &&
                    $request['exception']['message'] == $message;
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

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Http::assertNothingSent();
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

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_respects_the_traked_type_config()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        Config::set([
            'qmonitor.monitor_types.job' => false,
        ]);

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Http::assertNothingSent();
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

        tap($this->app->make(Dispatcher::class), function ($dispatcher) use ($syncJob) {
            $dispatcher->dispatch(new JobProcessing('sync', $syncJob));
        });

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_handles_encrypted_jobs()
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $job = new FakeEncryptedJob;

        $payload = $this->jobEventPayloadMock($job, 'sync');

        $syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            'sync',
            'default'
        );

        tap($this->app->make(Dispatcher::class), function ($dispatcher) use ($syncJob) {
            $dispatcher->dispatch(new JobProcessed('sync', $syncJob));
        });

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request['displayName'] == FakeEncryptedJob::class;
        });
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_dispatches_a_job_if_an_error_is_thrown()
    {
        Http::fake([
            'https://fail.qmonitor.io/*' => Http::response(['message' => 'Fail!'], 400),
        ]);

        Config::set([
            'qmonitor.endpoint' => 'https://fail.qmonitor.io',
        ]);

        Queue::fake();
        Queue::assertNothingPushed();

        tap($this->app->make(Dispatcher::class), function ($dispatcher) {
            $dispatcher->dispatch(new JobProcessing('sync', $this->syncJob));
        });

        Http::assertSentCount(2); // 2 retries
        Queue::assertPushed(QmonitorPingJob::class);
    }
}