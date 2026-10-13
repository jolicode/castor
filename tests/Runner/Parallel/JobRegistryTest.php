<?php

namespace Castor\Tests\Runner\Parallel;

use Castor\Runner\Parallel\Job;
use Castor\Runner\Parallel\JobRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class JobRegistryTest extends TestCase
{
    public function testTheCodeOutsideOfAJobHasNoIo(): void
    {
        $this->assertNull(new JobRegistry(new ArrayInput([]))->getCurrentIo());
    }

    /**
     * Nobody would see the question: the output of the job is captured.
     */
    public function testAQuestionAskedInAJobGetsItsDefaultAnswer(): void
    {
        $input = new ArrayInput([]);
        $input->setStream(fopen('php://memory', 'r') ?: throw new \RuntimeException('Could not open a memory stream.'));
        $jobRegistry = new JobRegistry($input);
        $output = new BufferedOutput();
        $job = new Job('test', $output, $output, static function (): void {}, static function (): void {});

        $fiber = new \Fiber(static fn () => $jobRegistry->getCurrentIo()?->ask('Name?', 'Bob'));
        $jobRegistry->attach($fiber, $job);
        $fiber->start();

        $this->assertSame('Bob', $fiber->getReturn());
        $this->assertTrue($input->isInteractive());
    }
}
