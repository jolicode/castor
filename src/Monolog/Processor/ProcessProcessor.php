<?php

namespace Castor\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\Process\Process;

class ProcessProcessor implements ProcessorInterface
{
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
        $runnable = $process->getCommandLine();

        foreach ($process->getEnv() as $key => $value) {
            if ('argv' === $key || 'argc' === $key) {
                continue;
            }
            $runnable = \sprintf('%s=%s %s ', $key, escapeshellarg($value), $runnable);
        }

        $runnable = rtrim($runnable, ' ');

        return [
            'cwd' => $process->getWorkingDirectory(),
            'env' => $process->getEnv(),
            'runnable' => $runnable,
        ];
    }
}
