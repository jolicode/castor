<?php

namespace Castor\Example;

use Castor\Attribute\Task;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Task(description: "A simple command that use symfony style")]
function output(SymfonyStyle $io) {
    $value = $io->ask('Tell me something');
    $io->writeln('You said: ' . $value);
}
