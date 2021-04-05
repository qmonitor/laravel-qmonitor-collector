<?php

namespace Qmonitor\Commands;

use Illuminate\Console\Command;
use Qmonitor\Jobs\QmonitorHeartbeatJob;

class QmonitorHeartbeat extends Command
{
    public $signature = 'qmonitor:heartbeat';

    public $description = 'Dispatch a queue job that will ping qmonitor.io heartbeat endpoint.';

    public function handle()
    {
        QmonitorHeartbeatJob::dispatch();

        $this->info('Heartbeat job dispatched!');
        $this->newLine();
    }
}
