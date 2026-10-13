<?php

namespace Castor\Tests\Llm;

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
}
