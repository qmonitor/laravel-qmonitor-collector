<?php

namespace Qmonitor\Jobs;

use Throwable;
use Qmonitor\Qmonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;

class QmonitorHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var integer
     */
    public $tries = 3;

    /**
     * @var integer
     */
    public $maxExceptions = 3;

    public function __construct()
    {
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
            Qmonitor::sendHeartbeat();
        } catch (RequestException $e) {
            Log::error('Could not reach '.parse_url($this->url(), PHP_URL_HOST), [
                'status' => $e->response->status() ?? null,
                'response' => $e->response['message'] ?? null,
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
        return Qmonitor::heartbeatUrl();
    }
}
