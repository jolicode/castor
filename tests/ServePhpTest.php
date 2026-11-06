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
        if (self::$binary) {
            $this->markTestSkipped('This test can not be ran with the binary version of Castor: it does not embed a PHP CLI.');
        }

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
        if (self::$binary) {
            $this->markTestSkipped('This test can not be ran with the binary version of Castor: it does not embed a PHP CLI.');
        }

        $process = $this->runTask(['web-without-document-root'], '{{ base }}/tests/fixtures/valid/serve-php');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('The PHP web server could not start', $process->getErrorOutput());
    }

    public function testStaticBinaryWithoutPhpCli(): void
    {
        if (!self::$binary) {
            $this->markTestSkipped('This test is for the binary version of Castor.');
        }

        $process = $this->runTask(['web'], '{{ base }}/tests/fixtures/valid/serve-php');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('--php-cli', $process->getErrorOutput());
    }
}
