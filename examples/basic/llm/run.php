<?php

namespace llm;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\llm_run;

#[AsTask(description: 'Fix the typos of the README with an LLM')]
function fix_readme_typos(): void
{
    $report = llm_run(<<<'PROMPT'
        Fix the typos in the README.md file of this project, without changing its meaning nor its formatting.
        Then answer with the list of the typos you fixed, or "No typo found".
        PROMPT);

    io()->writeln($report);
}
