<?php

namespace Qmonitor\Tests\Fixtures;

class FakeJobWithEloquentCollection
{
    public $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }
}
