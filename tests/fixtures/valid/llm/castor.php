<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\llm_ask;
use function Castor\llm_run;

#[AsTask(description: 'Ask a question to a fake LLM CLI')]
function ask(): void
{
    io()->writeln(llm_ask('What is the answer?', cli: ['sh', __DIR__ . '/fake-llm.sh']));
}

#[AsTask(name: 'ask-in-argument', description: 'Ask a question that is not valid UTF-8 to a LLM CLI reading it as an argument')]
function ask_in_argument(): void
{
    io()->writeln(llm_ask("What is the answer?\xFF", cli: ['printf', 'You asked: %s\nThe answer is 42.\n', '{prompt}']));
}

#[AsTask(name: 'ask-default', description: 'Ask a question to the configured LLM CLI')]
function ask_default(): void
{
    io()->writeln(llm_ask('What is the answer?'));
}

#[AsTask(description: 'Let a fake LLM CLI work on the project')]
function run(): void
{
    io()->writeln(llm_run('Fix the typos.', cli: ['sh', __DIR__ . '/fake-llm.sh', 'write']));
}

#[AsTask(name: 'run-default', description: 'Let the configured LLM CLI work on the project')]
function run_default(): void
{
    io()->writeln(llm_run('Fix the typos.'));
}
