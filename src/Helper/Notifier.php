<?php

namespace Castor\Helper;

use Joli\JoliNotif\Exception\InvalidNotificationException;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\Notifier as JoliNotifier;
use Joli\JoliNotif\Notifier\NullNotifier;
use Psr\Log\LoggerInterface;

use function Castor\context;

class Notifier
{
    public function __construct(
        private JoliNotifier $notifier,
        private LoggerInterface $logger,
    ) {
    }

    public function send(string $message, ?string $title = null): void
    {
        $notification = (new Notification())
            ->setTitle($title ?? $this->getNotifyTitle())
            ->setBody($message)
        ;

        if ($this->notifier instanceof NullNotifier) {
            $this->logger->warning('No supported notifier found, notification not sent.');

            return;
        }

        try {
            $success = $this->notifier->send($notification);

            if (!$success) {
                $this->logger->error('Failed to send notification.');
            }
        } catch (InvalidNotificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send notification: ' . $e->getMessage());
        }
    }

    private function getNotifyTitle(): string
    {
        if ('' !== context()->notificationTitle) {
            return context()->notificationTitle;
        }

        return 'Castor';
    }
}
