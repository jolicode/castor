<?php

namespace Castor\Tests\Helper;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Helper\Waiter;
use Castor\Runner\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Process\ExecutableFinder;

class WaiterTest extends TestCase
{
    /**
     * The container name is passed to docker as an argument, never through a
     * shell, so it cannot run anything even when it comes from user input.
     */
    public function testTheDockerContainerNameIsNotInterpretedByAShell(): void
    {
        if (null === new ExecutableFinder()->find('docker')) {
            $this->markTestSkipped('docker is not installed.');
        }

        $commands = [];

        $processRunner = $this->createStub(ProcessRunner::class);
        $processRunner
            ->method('capture')
            ->willReturnCallback(static function (string|array $command) use (&$commands): string {
                $commands[] = $command;

                return 'abc123';
            })
        ;

        $contextRegistry = $this->createStub(ContextRegistry::class);
        $contextRegistry->method('getCurrentContext')->willReturn(new Context());

        $waiter = new Waiter(new MockHttpClient(), $processRunner, $contextRegistry);
        $waiter->waitForDockerContainer(new SymfonyStyle(new ArrayInput([]), new NullOutput()), 'db; touch /tmp/pwned', quiet: true);

        $this->assertSame([
            ['docker', 'ps', '-a', '-q', '--filter', 'name=db; touch /tmp/pwned'],
            ['docker', 'inspect', '-f', '{{.State.Running}}', 'abc123'],
        ], $commands);
    }
}
