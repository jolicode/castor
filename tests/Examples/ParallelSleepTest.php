<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use PHPUnit\Framework\ExpectationFailedException;

class ParallelSleepTest extends TaskTestCase
{
    // parallel:sleep
    public function test(): void
    {
        $process = $this->runTask(['parallel:sleep', '--sleep5', '0', '--sleep7', '0', '--sleep10', '0']);

        $this->assertSame(0, $process->getExitCode());
        // normalize line endings
        $output = str_replace("\r", '', $process->getOutput());

        $startWith = <<<'OUTPUT'
            sleep 0
            sleep 0
            re sleep 0
            sleep 0
            OUTPUT;

        try {
            $this->assertStringStartsWith($startWith, $output);
        } catch (ExpectationFailedException) {
            // The order of the fibers might be different. So we try another
            // order.
            $startWith = <<<'OUTPUT'
                sleep 0
                sleep 0
                sleep 0
                re sleep 0
                OUTPUT;
            $this->assertStringStartsWith($startWith, $output);
        }

        $endWith = <<<'OUTPUT'
            Foo: foo
            Bar: bar
            Sleep 10: sleep 0
            Duration: 0

            OUTPUT;
        $this->assertStringEndsWith($endWith, $output);
        $this->assertSame('', $process->getErrorOutput());
    }
}
