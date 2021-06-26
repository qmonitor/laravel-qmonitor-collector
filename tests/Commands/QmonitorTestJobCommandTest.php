<?php

namespace Qmonitor\Tests\Commands;

use Illuminate\Support\Facades\Queue;
use Qmonitor\Jobs\QmonitorTestJob;
use Qmonitor\Tests\TestCase;

class QmonitorTestJobCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function it_dispatches_a_test_job()
    {
        $this->artisan('qmonitor:test')
            ->expectsOutput('Test job dispatched!')
            ->assertExitCode(0);

        Queue::assertPushed(QmonitorTestJob::class);
    }
}
