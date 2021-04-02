<?php

namespace Qmonitor\Jobs;

use Throwable;
use Qmonitor\Qmonitor;
use Illuminate\Bus\Queueable;
use Qmonitor\Support\JobPayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Qmonitor\Support\QmonitorJobPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;

class QmonitorPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array
     */
    public $payload;

    /**
     * @var integer
     */
    public $tries = 20;

    /**
     * @var integer
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     *
     * @param \Qmonitor\Support\QmonitorJobPayload $payload
     *
     * @return void
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;

        if ($connection = config('qmonitor.queue_connection')) {
            $this->onConnection($connection);
        }

        if ($queue = config('qmonitor.queue_name')) {
            $this->onQueue($queue);
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Qmonitor::sendPing($this->payload);
        } catch (RequestException $e) {
            Log::error('Could not reach '.parse_url($this->url(), PHP_URL_HOST), [
                'status' => $e->response->status() ?? null,
                'response' => $e->response->json('message') ?? null,
                'url' => $this->url(),
            ]);

            $this->release(15);
        } catch (Throwable $e) {
            report($e);
            $this->fail($e);
        }
    }

    public function url()
    {
        return Qmonitor::pingUrl();
    }
}
