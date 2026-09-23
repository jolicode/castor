<?php

namespace Castor\Tests\Llm;

use Castor\Llm\Cli;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public function testTheOptionsReplaceThePlaceholder(): void
    {
        $cli = new Cli('foo', ['foo', 'run', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER], modelOption: '-m', agentOption: '--agent');

        $this->assertSame(['foo', 'run', Cli::PROMPT_PLACEHOLDER], $cli->buildCommand());
        $this->assertSame(['foo', 'run', '-m', 'bar', '--agent', 'baz', '--extra', Cli::PROMPT_PLACEHOLDER], $cli->buildCommand('bar', 'baz', ['--extra']));
    }

    public function testTheDefaultAgentIsUsedWhenNoneIsChosen(): void
    {
        $cli = new Cli('foo', ['foo', Cli::OPTIONS_PLACEHOLDER], agentOption: '--agent', defaultAgent: 'plan');

        $this->assertSame(['foo', '--agent', 'plan'], $cli->buildCommand());
        $this->assertSame(['foo', '--agent', 'build'], $cli->buildCommand(agent: 'build'));
    }
}
