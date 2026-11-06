<?php

namespace Castor\Tests;

use Castor\Tests\Helper\WebServerTaskTrait;

/**
 * With a compiled binary, see CompileCommandTest::testWithPhpCli().
 */
class WebServerTaskTest extends TaskTestCase
{
    use WebServerTaskTrait;

    public function test(): void
    {
        if (self::$binary) {
            $this->markTestSkipped('This test can not be ran with the binary version of Castor: it does not embed a PHP CLI.');
        }

        $this->assertWebServerTaskServesPhpAndCleansUp(
            $this->getCommand(['web']),
            __DIR__ . '/fixtures/valid/web-server',
            [
                'CASTOR_CACHE_DIR' => self::$castorCacheDir,
                'CASTOR_DISABLE_AGENT_DETECTION' => 'true',
                'CASTOR_NO_REMOTE' => '1',
                'CASTOR_TEST' => 'true',
            ],
        );
    }
}
