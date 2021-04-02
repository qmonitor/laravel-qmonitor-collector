<?php

namespace Qmonitor\Tests\Fixtures;

class FakeJobEvent
{
    public $job;

    public function __construct($job)
    {
        $this->job = $job;
    }
}
