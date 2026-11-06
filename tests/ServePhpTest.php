<?php

namespace Castor\Tests;

use Castor\Tests\Helper\ServePhpTaskTrait;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * With a compiled binary, see CompileCommandTest::testWithPhpCli().
 */
class ServePhpTest extends TaskTestCase
{
    use ServePhpTaskTrait;

    #[TestWith(['web'])]
    #[TestWith(['web-with-callback'])]
    public function test(string $task): void
    {
        $this->assertServesPhpAndCleansUp(
            $this->getCommand([$task]),
            __DIR__ . '/fixtures/valid/serve-php',
            [
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => '1',
                'CASTOR_TEST' => 'true',
            ],
        );
    }

    public function testMissingDocumentRoot(): void
    {
        $process = $this->runTask(['web-without-document-root'], '{{ base }}/tests/fixtures/valid/serve-php');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('The PHP web server could not start', $process->getErrorOutput());
    }

    public function testStaticBinaryWithoutPhp(): void
    {
        if (!self::$binary) {
            $this->markTestSkipped('This test is for the binary version of Castor.');
        }

        $process = $this->runTask(['web'], '{{ base }}/tests/fixtures/valid/serve-php', env: ['PATH' => '/nonexistent']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('--php-cli', $process->getErrorOutput());
    }
}
