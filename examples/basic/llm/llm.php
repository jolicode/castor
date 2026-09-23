<?php

namespace llm;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\io;
use function Castor\llm;

#[AsTask(description: 'Summarize the last commits with an LLM')]
function summarize_commits(): void
{
    $commits = capture(['git', 'log', '--oneline', '--no-merges', '-n', '20']);

    $summary = llm(<<<PROMPT
        Summarize the following git commits in a few bullet points, for a changelog. Use only the commit messages below:

        {$commits}
        PROMPT);

    io()->writeln($summary);
}
