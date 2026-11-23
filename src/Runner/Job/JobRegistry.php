<?php

namespace Castor\Runner\Job;

use Castor\Context;
use Castor\ContextRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Knows the jobs of the command, and which one the code currently running
 * belongs to, to route its output to the display of the jobs.
 *
 * The job of the code is the one of its context, or else the one of the
 * function given to parallel() it runs in.
 *
 * The display is started with the first job, and stopped at the end of the
 * command.
 *
 * Not final, to be lazy loaded by the application.
 *
 * @internal
 */
class JobRegistry
{
    private ?JobDisplayInterface $display = null;
    /** @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, Job> */
    private \WeakMap $fiberJobs;
    /** @var \WeakMap<Job, SymfonyStyle> */
    private \WeakMap $ios;
    /** The level of the output buffer capturing what the jobs echo */
    private ?int $outputBufferLevel = null;
    /** @var array<string, Job> */
    private array $jobs = [];

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly ContextRegistry $contextRegistry,
    ) {
        $this->fiberJobs = new \WeakMap();
        $this->ios = new \WeakMap();
    }

    /**
     * Makes the code run by the fiber belong to the job, when its context
     * does not name one.
     *
     * @param \Fiber<mixed, mixed, mixed, mixed> $fiber
     */
    public function attach(\Fiber $fiber, Job $job): void
    {
        $this->fiberJobs[$fiber] = $job;
    }

    public function get(string $name): Job
    {
        return $this->jobs[$name] ??= $this->create($name);
    }

    /**
     * @param ?Context $context The context of the code, the current one by default
     */
    public function getCurrent(?Context $context = null): ?Job
    {
        $name = $context?->job;
        if (null === $name && $this->contextRegistry->hasCurrentContext()) {
            $name = $this->contextRegistry->getCurrentContext()->job;
        }

        if (null !== $name) {
            return $this->get($name);
        }

        $fiber = \Fiber::getCurrent();

        return $fiber ? $this->fiberJobs[$fiber] ?? null : null;
    }

    public function getCurrentIo(): ?SymfonyStyle
    {
        if (!$job = $this->getCurrent()) {
            return null;
        }

        if (!isset($this->ios[$job])) {
            // Nobody would see a question: the output of the job is
            // captured. The default answer is used instead.
            $input = clone $this->input;
            $input->setInteractive(false);
            $this->ios[$job] = new SymfonyStyle($input, $job->output);
        }

        return $this->ios[$job];
    }

    /**
     * Something starts running in the job.
     */
    public function begin(Job $job): void
    {
        if ($job->begin()) {
            $this->display?->update($job);
        }
    }

    /**
     * Something stops running in the job.
     */
    public function end(Job $job, ?\Throwable $exception = null): void
    {
        if ($job->end($exception)) {
            $this->display?->update($job);
        }
    }

    /**
     * Refreshes the display, called regularly while the jobs run.
     */
    public function tick(): void
    {
        $this->display?->tick();
    }

    /**
     * Gives the console back to the code that does not run in a job, before
     * it writes to it.
     */
    public function suspend(): void
    {
        $this->display?->suspend();
    }

    /**
     * Stops the display, once the command is finished.
     */
    public function stop(): void
    {
        if (null !== $this->outputBufferLevel) {
            // Another buffer may have been started after this one, and not
            // ended: it can not be ended in its place
            if (ob_get_level() === $this->outputBufferLevel) {
                ob_end_flush();
            }
            $this->outputBufferLevel = null;
        }

        $display = $this->display;
        $this->display = null;
        $this->jobs = [];

        $display?->stop();
    }

    private function create(string $name): Job
    {
        $errorOutput = $this->output instanceof ConsoleOutputInterface ? $this->output->getErrorOutput() : $this->output;

        $display = $this->display;
        if (!$display) {
            $display = $this->display = TuiJobDisplay::isSupported($this->output)
                ? new TuiJobDisplay($this->output)
                : new PrefixedJobDisplay($this->output, $errorOutput);

            // Without it, echo, print or var_dump() in a job would write
            // straight to the console
            ob_start($this->writeEcho(...), 1);
            $this->outputBufferLevel = ob_get_level();
        }

        $job = new Job(
            $name,
            $this->output,
            $errorOutput,
            fn (Job $job, string $line) => $this->writeLine($job, $line, false),
            fn (Job $job, string $line) => $this->writeLine($job, $line, true),
        );
        $display->add($job);

        return $job;
    }

    private function writeLine(Job $job, string $line, bool $isError): void
    {
        if ($this->display) {
            $this->display->addLine($job, $line, $isError);

            return;
        }

        // Written once the command is finished, like by a destructor
        $output = $this->output;
        if ($isError && $output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }
        $output->writeln($line, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }

    private function writeEcho(string $buffer): string
    {
        if ('' === $buffer) {
            return '';
        }

        if ($job = $this->getCurrent()) {
            // Shown even in quiet mode, like when it is not in a job
            $job->output->write($buffer, false, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

            return '';
        }

        $this->suspend();

        return $buffer;
    }
}
