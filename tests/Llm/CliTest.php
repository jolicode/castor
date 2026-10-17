<?php

namespace Castor\Tests\Llm;

use Castor\Exception\LlmException;
use Castor\Llm\Cli;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public function testTheOptionsReplaceThePlaceholder(): void
    {
        $cli = new Cli('foo', ['foo', 'run', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER], modelOption: '-m', agentOption: '--agent');

        $this->assertSame(['foo', 'run', Cli::PROMPT_PLACEHOLDER], $cli->buildReadOnlyCommand());
        $this->assertSame(['foo', 'run', '-m', 'bar', '--agent', 'baz', '--extra', Cli::PROMPT_PLACEHOLDER], $cli->buildReadOnlyCommand('bar', 'baz', ['--extra']));
    }

    public function testTheReadOnlyArgumentsComeFirst(): void
    {
        $cli = new Cli('foo', ['foo', Cli::OPTIONS_PLACEHOLDER, '--prompt', Cli::PROMPT_PLACEHOLDER], readOnlyArguments: ['--approval-mode', 'plan'], modelOption: '-m');

        $this->assertSame(['foo', '--approval-mode', 'plan', '--prompt', Cli::PROMPT_PLACEHOLDER], $cli->buildReadOnlyCommand());
        $this->assertSame(['foo', '--approval-mode', 'plan', '-m', 'bar', '--extra', '--prompt', Cli::PROMPT_PLACEHOLDER], $cli->buildReadOnlyCommand('bar', extraArguments: ['--extra']));
    }

    public function testTheReadOnlyAgentIsUsedWhenNoneIsChosen(): void
    {
        $cli = new Cli('foo', ['foo', Cli::OPTIONS_PLACEHOLDER], agentOption: '--agent', readOnlyAgent: 'plan');

        $this->assertSame(['foo', '--agent', 'plan'], $cli->buildReadOnlyCommand());
        $this->assertSame(['foo', '--agent', 'build'], $cli->buildReadOnlyCommand(agent: 'build'));
    }

    public function testTheWriteArgumentsReplaceTheReadOnlyOnes(): void
    {
        $cli = new Cli('foo', ['foo', Cli::OPTIONS_PLACEHOLDER], readOnlyArguments: ['--approval-mode', 'plan'], writeArguments: ['--approval-mode', 'yolo'], modelOption: '-m');

        $this->assertTrue($cli->supportsWrite());
        $this->assertSame(['foo', '--approval-mode', 'yolo', '-m', 'bar'], $cli->buildWriteCommand('bar'));
    }

    public function testTheReadOnlyAgentIsNotUsedInWriteMode(): void
    {
        $cli = new Cli('foo', ['foo', Cli::OPTIONS_PLACEHOLDER], writeArguments: [], agentOption: '--agent', readOnlyAgent: 'plan');

        $this->assertSame(['foo'], $cli->buildWriteCommand());
        $this->assertSame(['foo', '--agent', 'build'], $cli->buildWriteCommand(agent: 'build'));
    }

    public function testACliWithoutWriteArgumentsCannotModifyTheProject(): void
    {
        $cli = new Cli('foo', ['foo', Cli::OPTIONS_PLACEHOLDER]);

        $this->assertFalse($cli->supportsWrite());

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('The "foo" LLM CLI cannot modify the project nor run commands.');

        $cli->buildWriteCommand();
    }
}
