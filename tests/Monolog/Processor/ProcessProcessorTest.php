<?php

namespace Castor\Tests\Monolog\Processor;

use Castor\Monolog\Processor\ProcessProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProcessProcessorTest extends TestCase
{
    public function test(): void
    {
        $process = new Process(['ls', '-alh'], '/tmp', [
            'foo' => 'b\'"`\ar',
            'argc' => 3,
            'argv' => ['/home/foo/.local/bin//castor', 'builder', '-vvv'],
        ]);
        $log = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'new process',
            context: ['process' => $process],
        );
        $processor = new ProcessProcessor($process);

        $this->assertEquals(
            [
                'cwd' => '/tmp',
                'env' => [
                    'foo' => 'b\'"`\ar',
                    'argc' => 3,
                    'argv' => ['/home/foo/.local/bin//castor', 'builder', '-vvv'],
                ],
                'runnable' => <<<'TXT'
                    foo='b'\''"`\ar' 'ls' '-alh'
                    TXT,
            ],
            $processor($log)->context['process'],
        );
    }
}
