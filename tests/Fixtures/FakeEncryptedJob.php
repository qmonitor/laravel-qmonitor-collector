<?php

namespace Qmonitor\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;

class FakeEncryptedJob implements ShouldBeEncrypted
{
    public function handle()
    {
        //
    }
}
