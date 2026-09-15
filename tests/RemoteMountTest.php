<?php

namespace Castor\Tests;

use Symfony\Component\Filesystem\Filesystem;

class RemoteMountTest extends TaskTestCase
{
    public function testACastorComposerPackageCanBeMountedLikeALocalDirectory(): void
    {
        new Filesystem()->remove(__DIR__ . '/fixtures/valid/remote-mount/.castor/vendor');

        // No vendor => should download
        $process = $this->runTask(['remote:pyrech:hello-example'], '{{ base }}/tests/fixtures/valid/remote-mount', needRemote: true);

        if (0 !== $process->getExitCode()) {
            $this->fail($process->getErrorOutput());
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output_update.txt', $process->getOutput());

        // Vendor downloaded => should not download
        $process = $this->runTask(['remote:pyrech:hello-example'], '{{ base }}/tests/fixtures/valid/remote-mount', needRemote: true);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output_no_update.txt', $process->getOutput());
    }
}
