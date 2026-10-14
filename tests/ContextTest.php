<?php

namespace Castor\Tests;

use Castor\Context;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ContextTest extends TestCase
{
    public function testSupportsInteractionDefaultsToTrueWhenExplicitlySet(): void
    {
        $context = new Context(supportsInteraction: true);

        $this->assertTrue($context->supportsInteraction());
    }

    public function testWithSupportsInteractionReturnsNewInstance(): void
    {
        $context = new Context(supportsInteraction: true);
        $nonInteractive = $context->withSupportsInteraction(false);

        $this->assertNotSame($context, $nonInteractive);
        $this->assertTrue($context->supportsInteraction());
        $this->assertFalse($nonInteractive->supportsInteraction());
    }

    public function testSupportsInteractionIsPreservedAcrossOtherWithers(): void
    {
        $context = new Context(supportsInteraction: false)
            ->withWorkingDirectory('/tmp')
            ->withQuiet()
            ->withAllowFailure()
            ->withEnvironment(['FOO' => 'bar'])
        ;

        $this->assertFalse($context->supportsInteraction());
    }

    public function testToInteractiveSwitchesFlagsWhenEnvSupportsInteraction(): void
    {
        $context = new Context(supportsInteraction: true)->toInteractive();

        $this->assertTrue($context->tty);
        $this->assertNull($context->timeout);
        $this->assertTrue($context->allowFailure);
    }

    public function testToInteractiveThrowsWhenEnvDoesNotSupportInteraction(): void
    {
        $context = new Context(supportsInteraction: false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not interactive/');

        $context->toInteractive();
    }

    public function testTrappedSignalsAreEmptyByDefault(): void
    {
        $this->assertSame([], new Context()->trappedSignals);
    }

    public function testWithTrappedSignalsDefaultsToSigintAndSigterm(): void
    {
        $context = new Context()->withTrappedSignals();

        $this->assertSame([\SIGINT, \SIGTERM], $context->trappedSignals);
    }

    public function testWithTrappedSignalsAcceptsACustomListOfSignals(): void
    {
        $context = new Context()->withTrappedSignals([\SIGUSR1, \SIGUSR1, \SIGHUP]);

        $this->assertSame([\SIGUSR1, \SIGHUP], $context->trappedSignals);
    }

    public function testWithTrappedSignalsCanRestoreTheDefaultBehavior(): void
    {
        $context = new Context()->withTrappedSignals()->withTrappedSignals([]);

        $this->assertSame([], $context->trappedSignals);
    }

    public function testWithTrappedSignalsRejectsSignalsThatCannotBeCaught(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The signal "SIGKILL" cannot be trapped');

        new Context()->withTrappedSignals([\SIGINT, \SIGKILL]);
    }

    public function testWithTrappedSignalsRejectsSigstop(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The signal "SIGSTOP" cannot be trapped');

        new Context()->withTrappedSignals([\SIGSTOP]);
    }

    public function testTrappedSignalsArePreservedAcrossOtherWithers(): void
    {
        $context = new Context()
            ->withTrappedSignals([\SIGINT])
            ->withQuiet()
            ->withWorkingDirectory('/tmp')
        ;

        $this->assertSame([\SIGINT], $context->trappedSignals);
    }

    public function testToInteractiveCanBypassTheCheck(): void
    {
        $context = new Context(supportsInteraction: false)->toInteractive(throwOnNonInteractiveEnv: false);

        $this->assertTrue($context->tty);
        $this->assertNull($context->timeout);
        $this->assertTrue($context->allowFailure);
        $this->assertFalse($context->supportsInteraction());
    }

    public function testSupportsInteractionIsFalseWithoutControllingTerminal(): void
    {
        if (!Process::isPtySupported() || null === new ExecutableFinder()->find('setsid')) {
            $this->markTestSkipped('This test needs PTY support and the "setsid" command.');
        }

        // STDIN and STDOUT are a PTY, but setsid detaches the process from its
        // controlling terminal, so /dev/tty can't be opened
        $process = new Process(
            ['setsid', '-w', \PHP_BINARY, '-r', 'require $argv[1]; var_export((new Castor\Context(workingDirectory: "/"))->supportsInteraction());', __DIR__ . '/../vendor/autoload.php'],
            env: ['CI' => false],
        );
        $process->setPty(true);
        $process->mustRun();

        $this->assertSame('false', trim($process->getOutput()));
    }
}
