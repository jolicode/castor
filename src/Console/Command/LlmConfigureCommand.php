<?php

namespace Castor\Console\Command;

use Castor\Exception\LlmException;
use Castor\Llm\CliRegistry;
use Castor\Llm\ConfigStorage;
use Castor\Llm\LlmRunner;
use Castor\Llm\Selection;
use Castor\Llm\Selector;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\ExecutableFinder;

/** @internal */
#[AsCommand(
    name: 'castor:llm:configure',
    description: 'Configures what Castor does when a task asks a LLM with llm()',
)]
final readonly class LlmConfigureCommand
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
        private Selector $selector,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument(description: 'What llm() does: "ask", "auto", "choose", the name of a LLM CLI with its options (like "claude --model opus"), or a custom command')]
        ?string $value = null,
        #[Option(description: 'Configure all your projects instead of this one', shortcut: 'g')]
        bool $global = false,
        #[Option(description: 'Remove the configuration')]
        bool $reset = false,
    ): int {
        if ($reset) {
            $this->configStorage->remove($global);
            $io->success(\sprintf('The LLM configuration of %s has been removed.', $global ? 'all your projects' : 'this project'));

            return Command::SUCCESS;
        }

        if (null === $value) {
            if (!$input->isInteractive()) {
                $io->error('The run is not interactive: pass the value as an argument.');

                return Command::FAILURE;
            }

            $selection = $this->ask($io);

            if (!$global && !$input->hasParameterOption(['--global', '-g'], true)) {
                $global = 'all your projects' === $io->choice('Save this configuration for', ['this project', 'all your projects'], 'this project');
            }
        } else {
            try {
                $selection = Selection::fromValue($value, $this->clis);
            } catch (LlmException $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->configStorage->set($selection->toValue(), $global);

        if (null !== $selection->customCommand) {
            $this->warnIfNotFound($io, $selection->customCommand);
        }

        $io->success(\sprintf('Castor will %s, for %s.', $selection->describe(), $global ? 'all your projects' : 'this project'));
        $io->note(\sprintf('Run "castor castor:llm:debug" to check the configuration. For a single run, set the %s environment variable to "%s" instead.', LlmRunner::ENV_VAR, $selection->toValue()));

        return Command::SUCCESS;
    }

    private function ask(SymfonyStyle $io): Selection
    {
        $choice = $io->choice('When a task asks a LLM, Castor should', [
            Selection::MODE_ASK => 'ask for a confirmation, then use the first LLM CLI installed',
            Selection::MODE_AUTO => 'use the first LLM CLI installed, without asking',
            Selection::MODE_CHOOSE => 'let you choose the CLI, the model and the agent at each run',
            'fixed' => 'always use the CLI or the command you are about to choose',
        ], Selection::MODE_ASK);

        return 'fixed' === $choice ? $this->selector->select($io) : Selection::mode($choice);
    }

    /**
     * @param string|list<string> $command
     */
    private function warnIfNotFound(SymfonyStyle $io, string|array $command): void
    {
        $binary = \is_array($command) ? $command[0] : (preg_split('/\s+/', trim($command)) ?: [''])[0];

        if (null === new ExecutableFinder()->find($binary) && !is_executable($binary)) {
            $io->warning(\sprintf('The "%s" executable was not found in the PATH.', $binary));
        }
    }
}
