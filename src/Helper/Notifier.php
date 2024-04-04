<?php

namespace Castor\Helper;

use Joli\JoliNotif\Notification;
use Joli\JoliNotif\Notifier as JoliNotifier;

class Notifier
{
    public function __construct(
        private JoliNotifier $notifier
    ) {
    }

    public function send(string $message): void
    {
        $notification = (new Notification())
            ->setTitle('Castor')
            ->setBody($message)
        ;

        $this->notifier->send($notification);
    }
}
