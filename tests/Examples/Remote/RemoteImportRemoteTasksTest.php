<?php

namespace Castor\Tests\Examples\Remote;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;

class RemoteImportRemoteTasksTest extends TaskTestCase
{
    // remote-import:remote-tasks
    public function test(): void
    {
        new Filesystem()->remove(__DIR__ . '/../../../.castor/vendor');

        // No vendor => should download
        $process = $this->runTask(['remote-import:remote-tasks'], needRemote: true);

        if (0 !== $process->getExitCode()) {
            $this->fail($process->getErrorOutput());
        }

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringContainsString('Remote packages imported', $process->getErrorOutput());

        // Vendor downloaded => should not download
        $process = $this->runTask(['remote-import:remote-tasks'], needRemote: true);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringNotContainsString('Downloading remote packages', $process->getErrorOutput());

        // Force remotes update => should update
        $process = $this->runTask(['remote-import:remote-tasks', '--update-remotes'], needRemote: true);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringContainsString('Remote packages imported', $process->getErrorOutput());
    }
}
