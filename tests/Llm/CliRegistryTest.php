<?php

namespace Castor\Tests\Llm;

use Symfony\Component\ErrorHandler\BufferingLogger;

class CliRegistryTest extends LlmTestCase
{
    public function testAnInvalidConfigurationOfOpenCodeIsReportedOnlyWhenOpenCodeRuns(): void
    {
        $_SERVER['OPENCODE_CONFIG_CONTENT'] = '["not", "an", "object"]';
        $logger = new BufferingLogger();

        $clis = $this->createCliRegistry(['opencode'], logger: $logger);

        $this->assertSame([], $logger->cleanLogs(), 'The commands that do not run OpenCode do not read its configuration.');

        $config = $clis->getEnvironment($clis->get('opencode'))['OPENCODE_CONFIG_CONTENT'];

        $this->assertStringStartsWith('{"agent":{"castor-read-only":', $config);
        $this->assertSame([
            ['warning', 'The OPENCODE_CONFIG_CONTENT environment variable does not contain a JSON object, Castor ignores it when it runs OpenCode.', []],
        ], $logger->cleanLogs());
    }
}
