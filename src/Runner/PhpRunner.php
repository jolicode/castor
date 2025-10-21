<?php

namespace Castor\Runner;

use Castor\Context;
use Castor\ContextRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/** @internal */
class PhpRunner
{
    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        #[Autowire(lazy: true)]
        private readonly ProcessRunner $processRunner,
    ) {
    }

    /**
     * @param array<string|\Stringable>                      $arguments
     * @param (callable(string, string, Process) :void)|null $callback
     */
    public function run(
        string $phpPath,
        array $arguments = [],
        ?Context $context = null,
        ?callable $callback = null,
    ): Process {
        // get program path
        $castorPath = $_SERVER['argv'][0];
        $context ??= $this->contextRegistry->getCurrentContext();

        return $this->processRunner->run([$castorPath, ...$arguments], context: $context->withEnvironment([
            'CASTOR_PHP_REPLACE' => $phpPath,
        ]), callback: $callback);
    }
}
