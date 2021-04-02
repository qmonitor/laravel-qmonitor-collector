<?php

namespace Qmonitor\Commands;

use Illuminate\Console\Command;
use Qmonitor\Jobs\QmonitorTestJob;

class QmonitorTest extends Command
{
    public $signature = 'qmonitor:test';

    public $description = 'Dispatch a test job to check queue functionality';

    public function handle()
    {
        QmonitorTestJob::dispatch();

        $this->info('Test job dispatched!');
        $this->newLine();
    }
}
