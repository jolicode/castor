<?php

namespace Castor\Tests\Runner\Job;

use Castor\ContextRegistry;
use Castor\Runner\Job\JobAwareOutput;
use Castor\Runner\Job\JobRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class JobAwareOutputTest extends TestCase
{
    public function testItWritesToTheErrorOutputOfTheCurrentJob(): void
    {
        $mainOutput = new BufferedOutput();
        $jobRegistry = new JobRegistry(new ArrayInput([]), $mainOutput, $this->createStub(ContextRegistry::class));
        $output = new JobAwareOutput($jobRegistry, $mainOutput);

        try {
            $fiber = new \Fiber(static fn () => $output->writeln('in the job'));
            $jobRegistry->attach($fiber, $jobRegistry->get('lint'));
            $fiber->start();
            $output->writeln('outside of any job');
        } finally {
            $jobRegistry->stop();
        }

        $this->assertSame("[lint] in the job\noutside of any job\n", $mainOutput->fetch());
    }
}
