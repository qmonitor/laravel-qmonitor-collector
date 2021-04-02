<?php

namespace Qmonitor\Support;

class QmonitorSetupPayload
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @param array $data
     */
    public static function make(array $data)
    {
        return new static($data);
    }

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Array representation of the payload
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }
}
