<?php

namespace Qmonitor\Tests\Support;

use Exception;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Qmonitor\Qmonitor;
use Qmonitor\Support\QmonitorJobPayload;
use Qmonitor\Tests\Fixtures\FakeBatchableTestJob;
use Qmonitor\Tests\Fixtures\FakeEncryptedJob;
use Qmonitor\Tests\Fixtures\FakeJobEvent;
use Qmonitor\Tests\Fixtures\FakeJobWithEloquentCollection;
use Qmonitor\Tests\Fixtures\FakeJobWithEloquentModel;
use Qmonitor\Tests\Fixtures\FakeJobWithEloquentModelAndTags;
use Qmonitor\Tests\Fixtures\FakeMail;
use Qmonitor\Tests\Fixtures\FakeModel;
use Qmonitor\Tests\Fixtures\FakePassingTestJob;
use Qmonitor\Tests\TestCase;
use StdClass;

class QmonitorJobPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->job = new FakePassingTestJob;
        $this->connection = 'sync';

        $payload = $this->jobEventPayloadMock($this->job);

        $this->syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            $this->connection,
            'default'
        );

        $this->event = new JobProcessing($this->connection, $this->syncJob);
    }

    /** @test */
    public function it_resolves_the_job_unique_identifier()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertNotNull($jobPayload->uuid);
    }

    /** @test */
    public function it_resolves_the_job_name()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEquals(FakePassingTestJob::class, $jobPayload->displayName);
    }

    /** @test */
    public function it_resolves_the_event_type()
    {
        $jobPayload = QmonitorJobPayload::make(new JobProcessing($this->connection, $this->syncJob));
        $this->assertEquals('processing', $jobPayload->event);

        $jobPayload = QmonitorJobPayload::make(new JobProcessed($this->connection, $this->syncJob));
        $this->assertEquals('processed', $jobPayload->event);

        $jobPayload = QmonitorJobPayload::make(new JobFailed($this->connection, $this->syncJob, new Exception));
        $this->assertEquals('failed', $jobPayload->event);

        $jobPayload = QmonitorJobPayload::make(new FakeJobEvent($this->syncJob));
        $this->assertEquals('unknown', $jobPayload->event);
    }

    /** @test */
    public function it_determines_if_the_job_is_encrypted()
    {
        if (! interface_exists(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class)) {
            return $this->assertTrue(true);
        }

        // When
        $jobPayload = QmonitorJobPayload::make($this->event);
        $this->assertFalse($jobPayload->encrypted);

        $jobPayload->setPayload(
            $this->jobEventPayloadMock(new FakeEncryptedJob)
        );
        $this->assertTrue($jobPayload->encrypted);
    }

    /** @test */
    public function it_records_the_event_timestamp()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertNotNull($jobPayload->exactTimestamp);
    }

    /** @test */
    public function it_resolves_the_hostname()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEquals(gethostname(), $jobPayload->hostname);
    }

    /** @test */
    public function it_resolves_the_app_version()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEquals(app()->version(), $jobPayload->appVersion);
    }

    /** @test */
    public function it_resolves_the_php_version()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEquals(PHP_VERSION, $jobPayload->phpVersion);
    }

    /** @test */
    public function it_resolves_the_php_environment()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEquals('testing', $jobPayload->environment);
    }

    /** @test */
    public function it_resolves_the_collector_version()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEquals(Qmonitor::version(), $jobPayload->collectorVersion);
    }

    /** @test */
    public function it_resolves_the_type()
    {
        $jobPayload = QmonitorJobPayload::make($this->event);
        $this->assertEquals('job', $jobPayload->type);

        $jobPayload->setPayload($this->jobEventPayloadMock(new BroadcastEvent(new StdClass)));
        $this->assertEquals('broadcast', $jobPayload->type);

        $jobPayload->setPayload($this->jobEventPayloadMock(new CallQueuedListener('stdClass', 'method', [new StdClass])));
        $this->assertEquals('event', $jobPayload->type);

        $jobPayload->setPayload($this->jobEventPayloadMock(new SendQueuedMailable(new FakeMail)));
        $this->assertEquals('mail', $jobPayload->type);

        $jobPayload->setPayload($this->jobEventPayloadMock(new SendQueuedNotifications([], new StdClass, ['mail'])));
        $this->assertEquals('notification', $jobPayload->type);
    }

    /** @test */
    public function the_tags_method_overrides_the_auto_tags()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        $first = new FakeModel;
        $first->id = 1;

        $second = new FakeModel;
        $second->id = 2;

        $jobPayload->setPayload(
            $this->jobEventPayloadMock(new FakeJobWithEloquentModelAndTags($first, $second))
        );

        // Then
        $this->assertCount(2, $jobPayload->tags);
        $this->assertEquals('tag1', $jobPayload->tags[0]);
        $this->assertEquals('tag2', $jobPayload->tags[1]);
    }

    /** @test */
    public function it_determines_the_eloquent_model_tags()
    {
        $jobPayload = QmonitorJobPayload::make($this->event);

        $first = new FakeModel;
        $first->id = 1;

        $second = new FakeModel;
        $second->id = 2;

        $jobPayload->setPayload(
            $this->jobEventPayloadMock(new FakeJobWithEloquentModel($first, $second))
        );
        $this->assertEquals([FakeModel::class.':1', FakeModel::class.':2'], $jobPayload->tags);
    }

    /** @test */
    public function it_determines_tags_from_eloquent_collections()
    {
        $jobPayload = QmonitorJobPayload::make($this->event);

        $first = new FakeModel;
        $first->id = 3;

        $second = new FakeModel;
        $second->id = 4;

        $fakeCollection = new EloquentCollection([$first, $second]);

        $jobPayload->setPayload(
            $this->jobEventPayloadMock(new FakeJobWithEloquentCollection($fakeCollection))
        );
        $this->assertEquals([FakeModel::class.':3', FakeModel::class.':4'], $jobPayload->tags);
    }

    /** @test */
    public function it_respectes_the_tags_config_flag()
    {
        Config::set([
            'qmonitor.tags' => false,
        ]);

        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertEmpty($jobPayload->tags);
    }

    /** @test */
    public function it_resolves_the_exception_details()
    {
        // Give
        $event = new JobFailed($this->connection, $this->syncJob, new Exception($message = 'Exception message'));

        // When
        $jobPayload = QmonitorJobPayload::make($event);

        // Then
        $this->assertEquals($message, $jobPayload->exception['message']);
    }

    /** @test */
    public function it_returns_null_for_non_existing_prop()
    {
        // When
        $jobPayload = QmonitorJobPayload::make($this->event);

        // Then
        $this->assertNull($jobPayload->someNonExistingProp);
    }

    /** @test */
    public function it_resolves_the_batch()
    {
        if (! $this->app->bound(BatchRepository::class)) {
            return $this->assertTrue(true);
        }

        // Given
        $batch = $this->prepareBatch();
        $event = new JobProcessing($this->connection, $this->syncJob);

        // // When
        $jobPayload = QmonitorJobPayload::make($event);

        // Then
        $this->assertEquals($batch->id, $jobPayload->batch['id']);
        $this->assertEquals($batch->name, $jobPayload->batch['name']);
        $this->assertEquals($batch->totalJobs, $jobPayload->batch['totalJobs']);
        $this->assertEquals($batch->pendingJobs, $jobPayload->batch['pendingJobs']);
        $this->assertEquals($batch->processedJobs(), $jobPayload->batch['processedJobs']);
        $this->assertEquals($batch->failedJobs, $jobPayload->batch['failedJobs']);
        $this->assertEquals($batch->progress(), $jobPayload->batch['progress']);
        $this->assertEquals($batch->finishedAt, $jobPayload->batch['finishedAt']);
    }

    protected function prepareBatch()
    {
        if (! class_exists('CreateJobBatchesTable')) {
            $this->artisan('queue:batches-table')
                ->assertExitCode(0);
        }

        $this->artisan('migrate')->assertExitCode(0);

        $pendingBatch = Bus::batch([
            $job = new FakeBatchableTestJob,
        ])->name('test batch');

        $repository = $this->app->make(BatchRepository::class);

        $batch = $repository->store($pendingBatch);
        $repository->incrementTotalJobs($batch->id, $pendingBatch->jobs->count());
        $job->withBatchId($batch->id);

        $payload = $this->jobEventPayloadMock($job, $this->connection);

        $this->syncJob = new SyncJob(
            $this->app->make(Container::class),
            json_encode($payload),
            $this->connection,
            'default'
        );

        return $batch->fresh();
    }
}
