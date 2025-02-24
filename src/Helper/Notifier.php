<?php

namespace Castor\Helper;

use Castor\ContextRegistry;
use Joli\JoliNotif\DefaultNotifier;
use Joli\JoliNotif\Exception\InvalidNotificationException;
use Joli\JoliNotif\Notification;
use Psr\Log\LoggerInterface;

use function Castor\context;

/** @internal */
class Notifier
{
    public function __construct(
        private readonly DefaultNotifier $notifier,
        private readonly LoggerInterface $logger,
        private readonly ContextRegistry $contextRegistry,
    ) {
    }

    public function send(string $message, ?string $title = null): void
    {
        if (false === $this->contextRegistry->getCurrentContext()->notify) {
            return;
        }

        $notification = (new Notification())
            ->setTitle($title ?? $this->getNotifyTitle())
            ->setBody($message)
        ;

        $driver = $this->notifier->getDriver();

        if (null === $driver) {
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
