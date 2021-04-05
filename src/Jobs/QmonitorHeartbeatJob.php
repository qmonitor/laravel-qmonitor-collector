<?php

namespace Qmonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Qmonitor\Qmonitor;
use Throwable;

class QmonitorHeartbeatJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
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
            Log::error('Could not reach '.parse_url(Qmonitor::heartbeatUrl(), PHP_URL_HOST), [
                'status' => $e->response->status() ?? null,
                'response' => $e->response['message'] ?? null,
                'url' => Qmonitor::heartbeatUrl(),
            ]);

            $this->release(15);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
