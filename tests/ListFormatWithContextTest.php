<?php

namespace Castor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ListFormatWithContextTest extends TaskTestCase
{
    /**
     * These formats describe the hidden "_complete" too, whose own "-c"
     * option must not conflict with the one of the context.
     */
    #[DataProvider('provideFormats')]
    public function test(string $format): void
    {
        $process = $this->runTask(['list', '--format', $format], '{{ base }}/tests/fixtures/valid/context-file');

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('--context', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFormats(): iterable
    {
        yield 'JSON' => ['json'];
        yield 'XML' => ['xml'];
    }
}
