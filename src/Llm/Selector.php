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

    /**
     * For the configuration, which llm_ask() and llm_run() share: the agent
     * is not asked, since an agent rarely suits both functions.
     */
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

        return self::REFUSE !== $name ? $this->completeWithAgent($io, $name) : null;
    }

    /**
     * Same as selectOrRefuse(), among the CLIs that can modify the project, for llm_run().
     */
    public function selectForWriteOrRefuse(SymfonyStyle $io): ?Selection
    {
        $name = $this->askCli($io, [self::CUSTOM_COMMAND, self::REFUSE], write: true);

        return self::REFUSE !== $name ? $this->completeWithAgent($io, $name) : null;
    }

    /**
     * @param list<string> $otherChoices The choices proposed after the installed CLIs
     * @param bool         $write        Only the CLIs that can modify the project
     *
     * @return string The name of the CLI, or one of the other choices
     */
    private function askCli(SymfonyStyle $io, array $otherChoices, bool $write = false): string
    {
        $installed = $write ? $this->clis->getInstalledSupportingWrite() : $this->clis->getInstalled();
        $names = $this->clis->getNames($installed);

        if (!$names) {
            $io->warning(\sprintf('None of the LLM CLIs Castor knows%s is installed: %s.', $write ? ' that can modify the project' : '', implode(', ', $this->clis->getNames($write ? $this->clis->allSupportingWrite() : null))));
        }

        // Pressing enter chooses, for llm_ask(), a CLI that may not modify the project when one is installed, and
        // for llm_run(), the first installed CLI that can modify it
        $preferred = $write ? ($installed[0] ?? null) : $this->clis->getDefault();
        $default = $preferred->name ?? $names[0] ?? self::CUSTOM_COMMAND;

        return $io->choice('Which LLM CLI do you want to use?', [...$names, ...$otherChoices], $default);
    }

    /**
     * Asks for what the chosen CLI needs: the command when it is a custom one, else the model.
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

        return Selection::forCli($cli, $model);
    }

    /**
     * Same as complete(), and the agent: the choice is kept for one function only.
     */
    private function completeWithAgent(SymfonyStyle $io, string $name): Selection
    {
        $selection = $this->complete($io, $name);

        if (null === $selection->cli || !$selection->cli->supportsAgent()) {
            return $selection;
        }

        return Selection::forCli($selection->cli, $selection->model, $this->askOne($io, 'Which agent?', $this->clis->listAgents($selection->cli)));
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
