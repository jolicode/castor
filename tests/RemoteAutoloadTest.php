<?php

namespace Castor\Tests;

use Symfony\Component\Filesystem\Filesystem;

class RemoteAutoloadTest extends TaskTestCase
{
    public function testTheAutoloadPathsAreRelativeToCastorComposerJsonWhenRunFromASubdirectory(): void
    {
        new Filesystem()->remove(__DIR__ . '/fixtures/valid/remote-autoload/.castor/vendor');

        $process = $this->runTask(['hello'], '{{ base }}/tests/fixtures/valid/remote-autoload/src', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from an autoloaded class', $process->getOutput());
    }

    public function testTheAutoloadPathsAreRelativeToCastorComposerJsonInTheCastorDirectory(): void
    {
        new Filesystem()->remove(__DIR__ . '/fixtures/valid/remote-autoload-in-castor-dir/.castor/vendor');

        $process = $this->runTask(['hello'], '{{ base }}/tests/fixtures/valid/remote-autoload-in-castor-dir', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from an autoloaded class', $process->getOutput());
    }
}
