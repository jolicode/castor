#!/usr/bin/env php
<?php

namespace My\Symfony\Application;

require_once __DIR__ . '/../vendor/autoload.php';

use Castor\Attribute\AsSymfonyTask;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('hello', 'Says hello from a symfony application')]
#[AsSymfonyTask(name: 'symfony:hello', console: [\PHP_BINARY, __FILE__])] // We need to force PHP binary to support windows
class HelloCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello');

        return 0;
    }
}

#[AsCommand('greet')]
#[AsSymfonyTask(name: 'symfony:greet', console: [\PHP_BINARY, __FILE__])] // We need to force PHP binary to support windows
class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('french', '-f', InputOption::VALUE_NONE, 'say it in french')
            ->addArgument('who', InputArgument::REQUIRED, 'who?')
            ->addArgument('suffix', InputArgument::OPTIONAL, 'Suffix something')
            ->addOption('punctuation', 'p', InputOption::VALUE_OPTIONAL, 'Add some punctuation', '!')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $greet = 'Hello';
        if ($input->getOption('french')) {
            $greet = 'Salut';
        }
        $who = $input->getArgument('who');
        $punctuation = $input->getOption('punctuation');
        $suffix = '';
        if ($input->hasArgument('suffix')) {
            $suffix = ' ' . $input->getArgument('suffix');
        }

        $output->writeln("{$greet} {$who}{$punctuation}{$suffix}");

        return 0;
    }
}

$app = new Application();
$app->addCommands([
    new HelloCommand(),
    new GreetCommand(),
]);

// Only run if it's the main entry point
if (__FILE__ === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $app->run();
}
