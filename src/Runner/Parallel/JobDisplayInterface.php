<?php

namespace Castor\Runner\Parallel;

/**
 * Shows the output of the jobs run by parallel() while they run.
 *
 * @internal
 */
interface JobDisplayInterface
{
    public function getJob(string $name): Job;

    /**
     * Called regularly while the jobs run.
     */
    public function tick(): void;

    /**
     * Called once the job is finished, and its error, if any, written to its
     * error output.
     */
    public function finish(Job $job): void;

    /**
     * Called once all the jobs are finished.
     */
    public function stop(): void;
}
