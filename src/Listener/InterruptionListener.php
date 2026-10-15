<?php

namespace Castor\Listener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * TODO: Remove this listener once Castor requires symfony/console ^8.2, which
 * exits with 128 + the signal number on its own (symfony/symfony#66950). It
 * only covers a signal received by Castor: the interruption of a process that
 * Castor did not see, handled by ProcessRunner, is still needed.
 *
 * @internal
 */
class InterruptionListener
{
    /**
     * Before 8.2, Symfony Console exits with the code 0 when a signal stops it,
     * so a CTRL+C would not stop "castor build && castor deploy". Castor exits
     * with 128 + the signal number instead, like a shell, whatever the signal,
     * as Symfony 8.2 does. Called first, so the other listeners and the
     * onSignals handlers of the task still decide.
     */
    #[AsEventListener(event: ConsoleEvents::SIGNAL, priority: 1024)]
    public function setExitCode(ConsoleSignalEvent $event): void
    {
        $event->setExitCode(128 + $event->getHandlingSignal());
    }
}
