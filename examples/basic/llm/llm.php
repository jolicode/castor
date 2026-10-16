<?php

namespace llm;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function Castor\capture;
use function Castor\io;
use function Castor\llm_ask;

#[AsTask(description: 'Summarize the last commits with an LLM')]
function summarize_commits(): void
{
    $commits = capture(['git', 'log', '--oneline', '--no-merges', '-n', '20']);

    $summary = llm_ask(<<<PROMPT
        Summarize the following git commits in a few bullet points, for a changelog. Use only the commit messages below:

        {$commits}
        PROMPT);

    // The answer may contain tags like "<info>", that the console would interpret
    io()->writeln(OutputFormatter::escape($summary));
}
