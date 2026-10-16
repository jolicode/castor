<?php

namespace Castor\Llm;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lets the user choose a LLM CLI, its model and its agent, interactively.
 *
 * @internal
 */
class Selector
{
    private const CUSTOM_COMMAND = 'A custom command';
    private const DEFAULT = 'The default of the CLI';
    private const OTHER = 'Another one';
    private const REFUSE = 'None, do not send the prompt';

    public function __construct(
        private readonly CliRegistry $clis,
    ) {
    }

    public function select(SymfonyStyle $io): Selection
    {
        return $this->complete($io, $this->askCli($io, [self::CUSTOM_COMMAND]));
    }

    /**
     * Before a prompt is sent: the user may also refuse to send it.
     *
     * @return ?Selection Null when the user refuses
     */
    public function selectOrRefuse(SymfonyStyle $io): ?Selection
    {
        $name = $this->askCli($io, [self::CUSTOM_COMMAND, self::REFUSE]);

        return self::REFUSE !== $name ? $this->complete($io, $name) : null;
    }

    /**
     * @param list<string> $otherChoices The choices proposed after the installed CLIs
     *
     * @return string The name of the CLI, or one of the other choices
     */
    private function askCli(SymfonyStyle $io, array $otherChoices): string
    {
        $names = array_map(static fn (Cli $cli): string => $cli->name, $this->clis->getInstalled());

        if (!$names) {
            $io->warning(\sprintf('None of the LLM CLIs Castor knows is installed: %s.', implode(', ', $this->clis->getNames())));
        }

        // Pressing enter chooses a CLI that may not modify the project, when one is installed
        $default = $this->clis->getDefault()->name ?? $names[0] ?? self::CUSTOM_COMMAND;

        return $io->choice('Which LLM CLI do you want to use?', [...$names, ...$otherChoices], $default);
    }

    /**
     * Asks for what the chosen CLI needs: the command when it is a custom one, else the model and the agent.
     */
    private function complete(SymfonyStyle $io, string $name): Selection
    {
        if (self::CUSTOM_COMMAND === $name) {
            $command = $io->ask(
                'Which command? The prompt is written on its standard input, and its standard output is the answer',
                validator: static fn (?string $command): string => trim((string) $command) ?: throw new \InvalidArgumentException('The command cannot be empty.'),
            );

            return Selection::forCustomCommand((string) $command);
        }

        $cli = $this->clis->get($name) ?? throw new \LogicException(\sprintf('Unknown LLM CLI "%s".', $name));

        $model = $cli->supportsModel() ? $this->askOne($io, 'Which model?', $this->clis->listModels($cli)) : null;
        $agent = $cli->supportsAgent() ? $this->askOne($io, 'Which agent?', $this->clis->listAgents($cli)) : null;

        return Selection::forCli($cli, $model, $agent);
    }

    /**
     * @param list<string> $choices
     */
    private function askOne(SymfonyStyle $io, string $question, array $choices): ?string
    {
        if ($choices) {
            $choice = $io->choice($question, [self::DEFAULT, ...$choices, self::OTHER], self::DEFAULT);

            if (self::DEFAULT === $choice) {
                return null;
            }

            if (self::OTHER !== $choice) {
                return $choice;
            }
        }

        $answer = trim((string) $io->ask(\sprintf('%s Leave empty for the default of the CLI', $question)));

        return '' !== $answer ? $answer : null;
    }
}
