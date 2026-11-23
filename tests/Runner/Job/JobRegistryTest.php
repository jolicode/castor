<?php

namespace Castor\Tests\Runner\Job;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Runner\Job\JobRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class JobRegistryTest extends TestCase
{
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
    }

    public function testTheCodeOutsideOfAJobHasNoIo(): void
    {
        $this->assertNull($this->createRegistry()->getCurrentIo());
    }

    /**
     * Nobody would see the question: the output of the job is captured.
     */
    public function testAQuestionAskedInAJobGetsItsDefaultAnswer(): void
    {
        $input = new ArrayInput([]);
        $input->setStream(fopen('php://memory', 'r') ?: throw new \RuntimeException('Could not open a memory stream.'));
        $jobRegistry = $this->createRegistry(input: $input);

        try {
            $fiber = new \Fiber(static fn () => $jobRegistry->getCurrentIo()?->ask('Name?', 'Bob'));
            $jobRegistry->attach($fiber, $jobRegistry->get('test'));
            $fiber->start();
        } finally {
            $jobRegistry->stop();
        }

        $this->assertSame('Bob', $fiber->getReturn());
        $this->assertTrue($input->isInteractive());
    }

    public function testTheJobOfTheContextComesFirst(): void
    {
        $jobRegistry = $this->createRegistry(new Context()->withJob('current'));

        try {
            $fiber = new \Fiber(static fn () => [
                $jobRegistry->getCurrent()?->name,
                $jobRegistry->getCurrent(new Context()->withJob('given'))?->name,
            ]);
            $jobRegistry->attach($fiber, $jobRegistry->get('fiber'));
            $fiber->start();

            $this->assertSame(['current', 'given'], $fiber->getReturn());
        } finally {
            $jobRegistry->stop();
        }
    }

    public function testTheJobOfTheFiberIsUsedWhenTheContextHasNone(): void
    {
        $jobRegistry = $this->createRegistry(new Context());

        try {
            $fiber = new \Fiber(static fn () => $jobRegistry->getCurrent()?->name);
            $jobRegistry->attach($fiber, $jobRegistry->get('fiber'));
            $fiber->start();

            $this->assertSame('fiber', $fiber->getReturn());
            $this->assertNull($jobRegistry->getCurrent());
        } finally {
            $jobRegistry->stop();
        }
    }

    public function testAJobCanRunSeveralTimes(): void
    {
        $jobRegistry = $this->createRegistry();
        $job = $jobRegistry->get('install');

        $jobRegistry->begin($job);
        $job->output->writeln('first');
        $jobRegistry->end($job);
        $this->assertFalse($job->isRunning());

        $jobRegistry->begin($job);
        $jobRegistry->begin($job);
        $job->output->writeln('second');
        $jobRegistry->end($job);
        $this->assertTrue($job->isRunning(), 'The job runs while something runs in it.');
        $jobRegistry->end($job);
        $jobRegistry->stop();

        $this->assertTrue($job->isSuccessful());
        $this->assertSame(<<<'TXT'
            [install] first
            [install] ✔ Done in X.Xs
            [install] second
            [install] ✔ Done in X.Xs

            TXT, preg_replace('{\d+\.\ds}', 'X.Xs', $this->output->fetch()));
    }

    public function testAFailedJobIsSummarized(): void
    {
        $jobRegistry = $this->createRegistry();
        $install = $jobRegistry->get('install');
        $test = $jobRegistry->get('test');

        $jobRegistry->begin($install);
        $jobRegistry->end($install);
        $jobRegistry->begin($test);
        $jobRegistry->end($test, new \RuntimeException('boom'));
        $jobRegistry->stop();

        $this->assertTrue($test->hasFailed());
        $this->assertSame(<<<'TXT'
            [install] ✔ Done in X.Xs
            [test]    ✘ Failed after X.Xs

            [install] ✔ Done in X.Xs
            [test]    ✘ Failed after X.Xs

            TXT, preg_replace('{\d+\.\ds}', 'X.Xs', $this->output->fetch()));
    }

    public function testWhatAJobEchoesIsCaptured(): void
    {
        $jobRegistry = $this->createRegistry(new Context()->withJob('echo'));
        $jobRegistry->get('echo');

        try {
            echo "echoed\n";
        } finally {
            $jobRegistry->stop();
        }

        $this->assertSame("[echo] echoed\n", $this->output->fetch());
    }

    private function createRegistry(?Context $currentContext = null, ?ArrayInput $input = null): JobRegistry
    {
        $contextRegistry = $this->createStub(ContextRegistry::class);
        $contextRegistry->method('hasCurrentContext')->willReturn(null !== $currentContext);
        if ($currentContext) {
            $contextRegistry->method('getCurrentContext')->willReturn($currentContext);
        }

        return new JobRegistry($input ?? new ArrayInput([]), $this->output, $contextRegistry);
    }
}
