<?php

namespace Castor\Monolog\Processor;

use Castor\Runner\ProcessRunner;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/** @internal  */
final readonly class ProcessProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(lazy: true)]
        private ProcessRunner $processRunner,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        foreach ($record->context as $key => $value) {
            if (!$value instanceof Process) {
                continue;
            }

            $record = $record->with(context: [
                ...$record->context,
                $key => $this->formatProcess($value),
            ]);
        }

        return $record;
    }

    /**
     * @return array{cwd: ?string, env: array<string, string>, runnable: string}
     */
    private function formatProcess(Process $process): array
    {
        return [
            'cwd' => $process->getWorkingDirectory(),
            'env' => $process->getEnv(),
            'runnable' => $this->processRunner->buildRunnableCommand($process),
        ];
    }
}
