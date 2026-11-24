<?php

namespace Castor\Exception;

/**
 * Thrown by parallel() and parallel_map() once all the functions are
 * finished, when some of them failed.
 */
class ParallelException extends \RuntimeException
{
    /**
     * @param array<array-key, \Throwable> $exceptions The exception of each function that failed, keyed like the functions
     * @param array<array-key, mixed>      $results    The result of each function that succeeded, keyed like the functions
     */
    public function __construct(
        public readonly array $exceptions,
        public readonly array $results,
    ) {
        // No previous exception: each one was already rendered when it was thrown
        parent::__construct('One or more exceptions were thrown in parallel.');
    }
}
