<?php

namespace Castor\Tests;

use Castor\Tests\Helper\OutputCleaner;

class RunPhpTest extends TaskTestCase
{
    // A tool run by run_php() that restarts itself - PHPStan, Rector and
    // composer/xdebug-handler all do - must run itself again, not Castor
    public function testAToolRestartingItselfRunsAgainWithItsOwnArguments(): void
    {
        $process = $this->runTask(['restart'], cwd: '{{ base }}/tests/fixtures/valid/run-php');

        $this->assertSame('', $process->getErrorOutput());
        $this->assertSame(0, $process->getExitCode());

        // The static binary is the PHP binary, and does not tell the script
        // about itself: there is nothing to restart with
        $expected = self::$binary ? <<<'TXT'
            restart skipped
            arguments: analyze src

            TXT : <<<'TXT'
            arguments: analyze src
            memory_limit: 512M

            TXT;

        $this->assertSame($expected, OutputCleaner::cleanOutput($process->getOutput()));
    }
}
