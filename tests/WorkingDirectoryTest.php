<?php

namespace Castor\Tests;

class WorkingDirectoryTest extends TaskTestCase
{
    public function testTheCurrentDirectoryIsTheProjectRootWhateverCastorIsInvokedFrom(): void
    {
        $process = $this->runTask(['cwd'], '{{ base }}/tests/fixtures/valid/working-directory/sub');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame(<<<'TXT'
            cwd: working-directory
            context: working-directory
            run: working-directory
            fs: found castor.php

            TXT, $process->getOutput());
    }

    public function testTheCurrentDirectoryFollowsAWithWorkingDirectoryBlock(): void
    {
        $process = $this->runTask(['cwd-with'], '{{ base }}/tests/fixtures/valid/working-directory');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame(<<<'TXT'
            inside: sub
            inside run: sub
            outside: working-directory

            TXT, $process->getOutput());
    }
}
