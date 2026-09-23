<?php

namespace Castor\Console\Command;

use Castor\Exception\LlmException;
use Castor\Helper\PlatformHelper;
use Castor\Llm\Cli;
use Castor\Llm\CliRegistry;
use Castor\Llm\ConfigStorage;
use Castor\Llm\LlmRunner;
use Castor\Llm\Selection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/** @internal */
#[AsCommand(
    name: 'castor:llm:debug',
    description: 'Shows which LLM CLI llm() uses, and why',
)]
final readonly class LlmDebugCommand
{
    public function __construct(
        // The services need the console input and output, which do not exist yet when the commands are registered
        #[Autowire(lazy: true)]
        private CliRegistry $clis,
        // The services need the console input and output, which do not exist yet when the commands are registered
        #[Autowire(lazy: true)]
        private ConfigStorage $configStorage,
        // The services need the console input and output, which do not exist yet when the commands are registered
        #[Autowire(lazy: true)]
        private LlmRunner $llmRunner,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Send a prompt to check the LLM CLI answers')]
        bool $test = false,
    ): int {
        $io->section('LLM CLIs');

        $io->table(['CLI', 'Status', 'Model option', 'Agent option'], array_map(function (Cli $cli): array {
            $path = $this->clis->getPath($cli);

            return [
                $cli->name,
                null !== $path ? \sprintf('<info>installed</info> (%s)', $path) : '<comment>not installed</comment>',
                $cli->modelOption ?? '',
                $cli->agentOption ?? '',
            ];
        }, $this->clis->all()));

        $io->section('Configuration');

        $notSet = '<comment>not set</comment>';
        $env = PlatformHelper::getEnv(LlmRunner::ENV_VAR);

        $io->table(['Source', 'Value'], [
            [\sprintf('%s environment variable', LlmRunner::ENV_VAR), false !== $env && '' !== trim($env) ? OutputFormatter::escape($env) : $notSet],
            ['Configuration of the project', null !== ($value = $this->configStorage->get(global: false)) ? OutputFormatter::escape($value) : $notSet],
            ['Global configuration', null !== ($value = $this->configStorage->get(global: true)) ? OutputFormatter::escape($value) : $notSet],
            ['Default', Selection::MODE_ASK],
        ]);

        try {
            [$selection, $source] = $this->llmRunner->resolve();
        } catch (LlmException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->text(\sprintf('Castor will <info>%s</info>, from %s.', $selection->describe(), $source));

        if ($selection->isFixed()) {
            $command = null !== $selection->cli
                ? $selection->cli->buildCommand($selection->model, $selection->agent, $selection->extraArguments)
                : $selection->customCommand;
            $commandLine = \is_array($command) ? new Process($command)->getCommandLine() : (string) $command;

            $io->text(\sprintf('The command is <comment>%s</comment>%s.', OutputFormatter::escape($commandLine), \is_array($command) && \in_array(Cli::PROMPT_PLACEHOLDER, $command, true) ? '' : ', with the prompt on its standard input'));
        } elseif (!$selection->isMode(Selection::MODE_CHOOSE)) {
            $installed = array_map(static fn (Cli $cli): string => $cli->name, $this->clis->getInstalled());

            $io->text($installed
                ? \sprintf('The CLIs are tried in this order: <info>%s</info>.', implode(', ', $installed))
                : '<error>None of the LLM CLIs Castor knows is installed.</error>');
        }

        $io->newLine();
        $io->text(\sprintf('Run "castor castor:llm:configure" to change it, or set the %s environment variable for a single run.', LlmRunner::ENV_VAR));

        if (!$test) {
            return Command::SUCCESS;
        }

        $io->section('Test');

        $start = microtime(true);

        try {
            $answer = $this->llmRunner->ask('Which LLM model are you? Answer in one short sentence.');
        } catch (LlmException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->text(OutputFormatter::escape($answer));
        $io->success(\sprintf('"%s" answered in %.1f seconds.', $this->llmRunner->getLastSelection()?->getName() ?? '?', microtime(true) - $start));

        return Command::SUCCESS;
    }
}
