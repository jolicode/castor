<?php

namespace Castor\Tests;

use Symfony\Component\Process\Exception\ProcessFailedException;

class CompletionWithoutRemotePackagesTest extends TaskTestCase
{
    private const string FIXTURE = '{{ base }}/tests/fixtures/valid/import-same-package-with-default-version';

    /**
     * A shell completion must never download anything: when the remote
     * packages are not installed, the completion runs without them.
     */
    public function test(): void
    {
        $process = $this->runTask(
            ['_complete', '--no-interaction', '-sbash', '-c1', '-a1', '-icastor', '-il'],
            self::FIXTURE,
            needRemote: true,
            needResetVendor: true,
        );

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertDirectoryDoesNotExist(str_replace('{{ base }}', __DIR__ . '/..', self::FIXTURE) . '/.castor/vendor');
        $this->assertStringNotContainsString('remote packages', $process->getOutput());
        $this->assertStringNotContainsString('Could not import', $process->getOutput());
        $this->assertStringContainsString('list', $process->getOutput());
    }
}
