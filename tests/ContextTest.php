<?php

namespace Castor\Tests;

use Castor\Context;
use PHPUnit\Framework\TestCase;

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
}
