<?php

namespace Castor\Tests;

class LlmConfigTest extends TaskTestCase
{
    private const CWD = '{{ base }}/tests/fixtures/valid/llm';

    public function testTheConfigurationDrivesTheLlmFunction(): void
    {
        // The configuration of the project chooses a custom command
        $process = $this->runTask(['castor:llm:configure', 'sh ./fake-llm.sh project'], self::CWD);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Castor will always run the command "sh ./fake-llm.sh project", for this project.', $process->getOutput());

        $process = $this->runTask(['ask-default'], self::CWD, needResetCache: false);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame("You asked: What is the answer?\nThe answer is 42. (project)\n", $process->getOutput());

        // The environment variable wins over the configuration
        $process = $this->runTask(['ask-default'], self::CWD, needResetCache: false, env: ['CASTOR_LLM' => 'sh ./fake-llm.sh env']);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame("You asked: What is the answer?\nThe answer is 42. (env)\n", $process->getOutput());

        // The debug command tells where the value comes from
        $process = $this->runTask(['castor:llm:debug'], self::CWD, needResetCache: false);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Castor will always run the command "sh ./fake-llm.sh project", from the configuration of the project.', $process->getOutput());

        // Without any configuration, a confirmation is needed, which a non-interactive run cannot give
        $process = $this->runTask(['castor:llm:configure', '--reset'], self::CWD, needResetCache: false);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('The LLM configuration of this project has been removed.', $process->getOutput());

        $process = $this->runTask(['ask-default'], self::CWD, needResetCache: false);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Castor needs a confirmation before sending a prompt to a LLM (from the default), but the run is not interactive.', $process->getErrorOutput());
    }
}
