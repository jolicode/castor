<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Knows which job the code currently running belongs to, to route its output.
 *
 * @internal
 */
final readonly class JobRegistry
{
    /** @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, Job> */
    private \WeakMap $jobs;
    /** @var \WeakMap<Job, SymfonyStyle> */
    private \WeakMap $ios;

    public function __construct(
        private InputInterface $input,
    ) {
        $this->jobs = new \WeakMap();
        $this->ios = new \WeakMap();
    }

    /**
     * @param \Fiber<mixed, mixed, mixed, mixed> $fiber
     */
    public function attach(\Fiber $fiber, Job $job): void
    {
        $this->jobs[$fiber] = $job;
    }

    public function getCurrent(): ?Job
    {
        $fiber = \Fiber::getCurrent();

        return $fiber ? $this->jobs[$fiber] ?? null : null;
    }

    public function getCurrentIo(): ?SymfonyStyle
    {
        if (!$job = $this->getCurrent()) {
            return null;
        }

        if (!isset($this->ios[$job])) {
            // Nobody would see a question: the output of the job is captured.
            // The default answer is used instead.
            $input = clone $this->input;
            $input->setInteractive(false);
            $this->ios[$job] = new SymfonyStyle($input, $job->output);
        }

        return $this->ios[$job];
    }
}
