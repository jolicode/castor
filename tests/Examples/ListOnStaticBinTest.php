<?php

namespace Castor\Tests\Examples;

use Castor\Tests\Helper\OutputCleaner;
use Castor\Tests\TaskTestCase;

class ListOnStaticBinTest extends TaskTestCase
{
    // list
    public function test(): void
    {
        if (!self::$binary) {
            $this->markTestSkipped('This test is for the binary version of Castor.');
        }

        $process = $this->runTask(['list', '--raw', '--format', 'txt', '--short']);

        $this->assertSame(0, $process->getExitCode());
        $expected = file_get_contents(__DIR__ . '/../Generated/ListTest.php.output.txt');
        $expected = preg_replace('{^(pyrech\:.*\n)}m', '', $expected);
        $expected = preg_replace('{^(symfony\:.*\n)}m', '', $expected);
        $this->assertSame($expected, OutputCleaner::cleanOutput($process->getOutput()));
        $this->assertSame('', $process->getErrorOutput());
    }
}
