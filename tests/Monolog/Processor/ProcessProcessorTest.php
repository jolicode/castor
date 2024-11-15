<?php

namespace Castor\Tests\Monolog\Processor;

use Castor\Monolog\Processor\ProcessProcessor;
use Castor\Runner\ProcessRunner;
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
        $mock = $this->getMockBuilder(ProcessRunner::class)
            ->onlyMethods(['buildRunnableCommand'])
            ->disableOriginalConstructor()
            ->getMock();

        $processor = new ProcessProcessor($mock);

        $this->assertEquals(
            [
                'cwd' => '/tmp',
                'env' => [
                    'foo' => 'b\'"`\ar',
                    'argc' => 3,
                    'argv' => ['/home/foo/.local/bin//castor', 'builder', '-vvv'],
                ],
                'runnable' => '',
            ],
            $processor($log)->context['process'],
        );
    }
}
