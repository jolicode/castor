<?php

namespace Castor\Tests\Runner\Parallel;

use Castor\Runner\Parallel\Job;
use Castor\Runner\Parallel\JobAwareOutput;
use Castor\Runner\Parallel\JobRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class JobAwareOutputTest extends TestCase
{
    public function testItWritesToTheErrorOutputOfTheCurrentJob(): void
    {
        $jobRegistry = new JobRegistry(new ArrayInput([]));
        $mainOutput = new BufferedOutput();
        $output = new JobAwareOutput($jobRegistry, $mainOutput);
        $lines = [];
        $job = new Job(
            'lint',
            $mainOutput,
            $mainOutput,
            static function (): void {},
            static function (Job $job, string $line) use (&$lines): void {
                $lines[] = $line;
            },
        );

        $fiber = new \Fiber(static fn () => $output->writeln('in the job'));
        $jobRegistry->attach($fiber, $job);
        $fiber->start();
        $output->writeln('outside of any job');

        $this->assertSame(['in the job'], $lines);
        $this->assertSame("outside of any job\n", $mainOutput->fetch());
    }
}
