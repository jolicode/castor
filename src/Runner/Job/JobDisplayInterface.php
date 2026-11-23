<?php

namespace Castor\Runner\Job;

/**
 * Shows the jobs and their output while they run.
 *
 * @internal
 */
interface JobDisplayInterface
{
    /**
     * Called once per job, before anything is written to it.
     */
    public function add(Job $job): void;

    /**
     * Called for each complete line written to the job.
     */
    public function addLine(Job $job, string $line, bool $isError): void;

    /**
     * Called when the job starts or stops running.
     */
    public function update(Job $job): void;

    /**
     * Called regularly while the jobs run.
     */
    public function tick(): void;

    /**
     * Called before the code that does not run in a job writes to the
     * console.
     */
    public function suspend(): void;

    /**
     * Called once, when the command is finished.
     */
    public function stop(): void;
}
