<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\llm;

#[AsTask(description: 'Ask a question to a fake LLM CLI')]
function ask(): void
{
    io()->writeln(llm('What is the answer?', cli: ['sh', __DIR__ . '/fake-llm.sh']));
}
