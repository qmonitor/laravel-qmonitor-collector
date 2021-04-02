<?php

namespace Qmonitor\Tests\Fixtures;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class FakePassingTestJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        //
    }

    public function tags(): array
    {
        return [
            'tag1', 'tag2',
        ];
    }
}
