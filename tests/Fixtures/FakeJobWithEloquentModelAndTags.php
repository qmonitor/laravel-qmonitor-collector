<?php

namespace Qmonitor\Tests\Fixtures;

class FakeJobWithEloquentModelAndTags
{
    public $nonModel;
    public $first;
    public $second;

    public function __construct($first, $second)
    {
        $this->nonModel = 1;
        $this->first = $first;
        $this->second = $second;
    }

    public function tags()
    {
        return ['tag1', 'tag2'];
    }
}
