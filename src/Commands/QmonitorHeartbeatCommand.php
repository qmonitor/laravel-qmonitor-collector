<?php

namespace Qmonitor\Commands;

use Illuminate\Console\Command;
use Qmonitor\Jobs\QmonitorHeartbeatJob;

class QmonitorHeartbeatCommand extends Command
{
    public $signature = 'qmonitor:heartbeat';

    public $description = 'Dispatch a queue job that will ping qmonitor.io heartbeat endpoint.';

    public function handle()
    {
        if (! config('qmonitor.enabled')) {
            $this->error('Qmonitor flag is set to OFF. Check the qmonitor config.');

            return static::FAILURE;
        }

        if (! config('qmonitor.app_id') || ! config('qmonitor.signing_secret')) {
            $this->error('Qmonitor app id or signing secret are not set. Make sure you ran the setup command.');

            return static::FAILURE;
        }

        QmonitorHeartbeatJob::dispatch();

        $this->info('Heartbeat job dispatched!');
        $this->newLine();
    }
}
