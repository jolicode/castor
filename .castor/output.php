<?php

namespace output;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsTask(description: 'A simple command that uses symfony style')]
function output(SymfonyStyle $io)
{
    $value = $io->ask('Tell me something');
    $io->writeln('You said: ' . $value);
}
