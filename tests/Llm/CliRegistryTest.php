<?php

namespace Castor\Tests\Llm;

use Castor\Exception\LlmException;

class CliRegistryTest extends LlmTestCase
{
    public function testTheConfigurationOfOpenCodeIsReadOnlyWhenOpenCodeRuns(): void
    {
        $_SERVER['OPENCODE_CONFIG_CONTENT'] = '["not", "an", "object"]';

        // The commands that do not run OpenCode do not read its configuration
        $clis = $this->createCliRegistry(['opencode']);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('The OPENCODE_CONFIG_CONTENT environment variable must contain a JSON object, without comments nor trailing commas, so that Castor can add its read-only agent to it.');

        $clis->getEnvironment($clis->get('opencode'));
    }

    public function testAConfigurationOfOpenCodeWithCommentsIsNotDropped(): void
    {
        // Valid for OpenCode, which would lose its provider if Castor replaced the value
        $_SERVER['OPENCODE_CONFIG_CONTENT'] = <<<'JSONC'
            {
                // The local models
                "provider": {"ollama": {}},
            }
            JSONC;
        $clis = $this->createCliRegistry(['opencode']);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('without comments nor trailing commas');

        $clis->getEnvironment($clis->get('opencode'));
    }
}
