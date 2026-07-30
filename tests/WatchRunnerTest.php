<?php

namespace Castor\Tests;

use Castor\Console\Application;
use Castor\Console\Output\SectionOutput;
use Castor\Context;
use Castor\ContextRegistry;
use Castor\Helper\Architecture;
use Castor\Helper\Installation;
use Castor\Runner\ParallelRunner;
use Castor\Runner\ProcessRunner;
use Castor\Runner\WatchRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Process;

class WatchRunnerTest extends TestCase
{
    public function testWatchingASinglePathStartsOneWatcher(): void
    {
        $this->assertSame(['/path/to/watch'], $this->getWatchedPaths('/path/to/watch'));
    }

    public function testWatchingAnArrayOfPathsStartsOneWatcherPerPath(): void
    {
        $this->assertSame(['/path/to/watch', '/another/path'], $this->getWatchedPaths(['/path/to/watch', '/another/path']));
    }

    /**
     * Runs the watcher on the given path(s), and returns the paths the spawned
     * watcher processes would have watched. The watcher binary is never really
     * started.
     *
     * @param string|non-empty-array<string> $path
     *
     * @return list<string>
     */
    private function getWatchedPaths(string|array $path): array
    {
        $watchedPaths = [];

        $installation = $this->createStub(Installation::class);
        $installation->method('getArchitecture')->willReturn(Architecture::Amd64);

        $processRunner = $this->createStub(ProcessRunner::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(static function (array $command) use (&$watchedPaths): Process {
                $watchedPaths[] = $command[1];

                return new Process($command);
            })
        ;

        new WatchRunner(
            $this->createStub(ContextRegistry::class),
            new ParallelRunner($this->createStub(Application::class), new NullOutput()),
            $processRunner,
            $this->createStub(SectionOutput::class),
            $installation,
        )->watch($path, static function (): void {}, new Context());

        return $watchedPaths;
    }
}
