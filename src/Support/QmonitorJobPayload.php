<?php

namespace Qmonitor\Support;

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Qmonitor\Qmonitor;

class QmonitorJobPayload
{
    /**
     * The event
     *
     * @var object
     */
    protected $event;

    /**
     * The job
     *
     * @var object
     */
    protected $job;

    /**
     * The payload
     *
     * @var array
     */
    protected $payload;

    /**
     * The comand
     *
     * @var mixed
     */
    protected $command;

    /**
     * The prepared data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Prepare paylod for queue event
     *
     * @param  mixed $event
     *
     * @return self
     */
    public static function make($event)
    {
        return new static($event);
    }

    /**
     * Create a new raw job payload instance.
     *
     * @param  object  $event
     * @return void
     */
    public function __construct(object $event)
    {
        $this->event = $event;
        $this->job = $event->job;
        $this->payload = $event->job->payload();
        $this->command = $this->command();

        $this->prepare();
    }

    public function setPayload($payload)
    {
        $this->payload = $payload;
        $this->command = $this->command();
        $this->prepare();

        return $this;
    }

    /**
     * Prepare the payload for storage on the queue by adding tags, etc.
     *
     * @return $this
     */
    protected function prepare()
    {
        return $this->set([
            'exactTimestamp' => now()->getPreciseTimestamp(3),
            'uuid' => $this->job->uuid(),
            'displayName' => $this->job->resolveName(),
            'type' => $this->determineType(),
            'tags' => $this->determineTags(),
            'maxTries' => $this->payload['maxTries'] ?? null,
            'maxExceptions' => $this->payload['maxExceptions'] ?? null,
            'backoff' => $this->payload['backoff'] ?? null,
            'delay' => $this->payload['delay'] ?? null,
            'timeout' => $this->payload['timeout'] ?? null,
            'retryUntil' => $this->payload['retryUntil'] ?? null,
            'attempts' => $this->job->attempts(),
            'connection' => $this->job->getConnectionName(),
            'queue' => $this->job->getQueue(),
            'released' => $this->job->isReleased(),
            'failed' => $this->job->hasFailed(),
            'isRetry' => isset($this->payload['retry_of']),
            'retryOf' => $this->payload['retry_of'] ?? null,
            'event' => $this->determineEventType(),
            'exception' => $this->getExceptionData(),
            'batch' => ! empty($this->command->batchId) ? $this->findBatch($this->command->batchId) : null,
            'pendingJobs' => $this->getQueueSize(),
            'hostname' => gethostname(),
            'environment' => app()->environment(),
            'appVersion' => app()->version(),
            'phpVersion' => phpversion(),
            'collectorVersion' => Qmonitor::version(),
        ]);
    }

    /**
     * Get the "type" of job being queued.
     *
     * @param  mixed  $job
     * @return string
     */
    protected function determineType()
    {
        switch (true) {
            case $this->command instanceof BroadcastEvent:
                return 'broadcast';
            case $this->command instanceof CallQueuedListener:
                return 'event';
            case $this->command instanceof SendQueuedMailable:
                return 'mail';
            case $this->command instanceof SendQueuedNotifications:
                return 'notification';
            default:
                return 'job';
        }
    }

    /**
     * Get event "type"
     *
     * @return string
     */
    protected function determineEventType(): string
    {
        switch (true) {
            case $this->event instanceof JobProcessing:
                return 'processing';
            case $this->event instanceof JobProcessed:
                return 'processed';
            case $this->event instanceof JobFailed:
                return 'failed';
            default:
                return 'unknown';
        }
    }

    /**
     * Get the appropriate tags for the job.
     *
     * @param  mixed  $job
     * @return array
     */
    protected function determineTags()
    {
        if (! Qmonitor::tagsEnabled()) {
            return [];
        }

        return ExtractTags::for($this->command);
    }

    /**
     * Set the given key / value pairs on the payload.
     *
     * @param  array  $values
     * @return $this
     */
    public function set(array $values)
    {
        $this->data = array_merge($this->data, $values);

        return $this;
    }

    /**
     * Get the "command" for the job.
     *
     * @return mixed
     */
    public function command()
    {
        $command = Arr::get($this->payload, 'data.command');

        try {
            $command = app()
                ->make(Encrypter::class)
                ->decrypt($command);

            $this->set(['encrypted' => true]);
        } catch (DecryptException $e) {
            $this->set(['encrypted' => false]);
        }

        return unserialize($command);
    }

    /**
     * Get queue size for queue
     *
     * @return int
     */
    public function getQueueSize()
    {
        return app()
            ->make(QueueManager::class)
            ->connection($this->job->getConnectionName())
            ->size();
    }

    /**
     * Find batch by id
     *
     * @param  string $batchId
     *
     * @return array|null
     */
    protected function findBatch($batchId)
    {
        if (! $batchId) {
            return;
        }

        $batch = app()->make(BatchRepository::class)->find($batchId);

        if (! $batch) {
            return;
        }

        return Arr::only(
            $batch->toArray(),
            ['id', 'name', 'totalJobs', 'pendingJobs', 'processedJobs', 'progress', 'failedJobs', 'finishedAt']
        );
    }

    /**
     * Get exception details
     *
     * @return array
     */
    public function getExceptionData()
    {
        if (! property_exists($this->event, 'exception')) {
            return [];
        }

        return [
            'name' => get_class($this->event->exception),
            'message' => $this->event->exception->getMessage(),
            'file' => str_replace(base_path(), '', $this->event->exception->getFile()),
            'line' => $this->event->exception->getLine(),
        ];
    }

    /**
     * Return array representation
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Map array keys as properties
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        if (! array_key_exists($key, $this->data)) {
            return null;
        }

        return $this->data[$key];
    }
}
