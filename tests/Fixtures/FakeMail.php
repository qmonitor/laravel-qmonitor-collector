<?php

namespace Qmonitor\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

class FakeMail extends Mailable
{
    use Queueable;

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return new MailMessage;
    }
}
