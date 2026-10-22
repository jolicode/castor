<?php

namespace Castor\Console\Input;

use Castor\Console\Application;
use Castor\Console\Command\SelfUpdateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * What the command line asks for, read once when Castor starts, before the
 * castor file is loaded: whether to load it, which context to use, whether to
 * install the remote packages, or whether anything else than the output of
 * the command can be written, are decided from it.
 *
 * Only the built-in commands are registered at that time, so they are
 * recognized by their exact name or alias only: an abbreviation could match a
 * task once the castor file is loaded. Once Symfony resolved the command, it
 * is used instead, so an abbreviation is recognized too. A task is never
 * recognized: task() tells which command runs.
 *
 * @internal
 */
final readonly class CommandLine
{
    /** The built-in command asked for, by its name or one of its aliases */
    private ?Command $builtInCommand;
    private ?string $contextName;
    private ?string $format;
    private bool $noRemote;
    private bool $updateRemotes;

    public function __construct(
        InputInterface $input,
        private Application $application,
    ) {
        // On a copy, to leave the input untouched for the castor file
        $this->builtInCommand = $this->findBuiltInCommand(clone $input);

        // The options are looked for among the raw tokens, wherever they are, since
        // the options of the command, not known yet, would stop the parsing. But not
        // after "--": what follows belongs to the task. The shell completion has its
        // own "-c" option, the position of the cursor
        $this->contextName = $input->getParameterOption($this->isShellCompletion() ? '--context' : ['--context', '-c'], null, onlyParams: true) ?: null;
        $this->format = $input->getParameterOption('--format', 'txt', onlyParams: true);
        $this->noRemote = $input->hasParameterOption('--no-remote', onlyParams: true);
        $this->updateRemotes = $input->hasParameterOption('--update-remotes', onlyParams: true);
    }

    public function isSelfUpdate(): bool
    {
        return SelfUpdateCommand::NAME === $this->getCommand()?->getName();
    }

    /**
     * Whether the shell asks for completion suggestions ("_complete"), which
     * it does at each key press.
     */
    public function isShellCompletion(): bool
    {
        return $this->getCommand() instanceof CompleteCommand;
    }

    /**
     * Whether the completion script is dumped ("completion"), to be loaded by
     * the shell.
     */
    public function isCompletionScript(): bool
    {
        return $this->getCommand() instanceof DumpCompletionCommand;
    }

    public function isList(): bool
    {
        return $this->getCommand() instanceof ListCommand;
    }

    /**
     * Whether the output is meant to be parsed, by the shell or because of a
     * format like in "list --format=json": nothing else must be written in it.
     */
    public function isOutputMachineReadable(): bool
    {
        return $this->isShellCompletion() || $this->isCompletionScript() || 'txt' !== $this->format;
    }

    /**
     * The context given with "--context" or "-c", null when there is none.
     */
    public function getContextName(): ?string
    {
        return $this->contextName;
    }

    public function hasNoRemote(): bool
    {
        return $this->noRemote;
    }

    public function hasUpdateRemotes(): bool
    {
        return $this->updateRemotes;
    }

    private function getCommand(): ?Command
    {
        return $this->application->getCommand(allowNull: true) ?? $this->builtInCommand;
    }

    private function findBuiltInCommand(InputInterface $input): ?Command
    {
        // The context option is only declared once the contexts are loaded,
        // like in Kernel::addContextOption()
        $definition = clone $this->application->getDefinition();
        $definition->addOption(new InputOption('context', 'c', InputOption::VALUE_REQUIRED));

        // Bound like in the doRun() of Symfony, so the value of an option, like in
        // "--castor-file my-file.php self-update" or "-c prod self-update", is not
        // taken for the command name
        try {
            $input->bind($definition);
        } catch (ExceptionInterface) {
            // The options of the command are not known yet
        }

        $name = $input->getFirstArgument();

        return null !== $name && $this->application->has($name) ? $this->application->get($name) : null;
    }
}
