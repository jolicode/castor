<?php

namespace Castor\Llm;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\LlmException;
use Castor\Helper\PlatformHelper;
use Castor\Runner\ProcessRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\String\u;

/** @internal */
class LlmRunner
{
    public const ENV_VAR = 'CASTOR_LLM';

    private const PREVIEW_LINES = 5;

    /**
     * In the "ask", "auto" and "choose" modes, the CLI that answered or that
     * was chosen, reused for the next questions of the run.
     */
    private ?Selection $runSelection = null;

    private bool $confirmed = false;

    private ?Selection $lastSelection = null;

    public function __construct(
        private readonly CliRegistry $clis,
        private readonly ConfigStorage $configStorage,
        private readonly Selector $selector,
        private readonly ContextRegistry $contextRegistry,
        private readonly ProcessRunner $processRunner,
        private readonly Filesystem $fs,
        private readonly LoggerInterface $logger,
        private readonly InputInterface $input,
        private readonly SymfonyStyle $io,
    ) {
    }

    /**
     * What llm() does, from the "cli" argument, then the CASTOR_LLM
     * environment variable, the configuration of the project, the global
     * configuration, or the default: asking before using the first CLI found.
     *
     * @param string|list<string>|null $cli
     *
     * @return array{Selection, string} The selection, and where it comes from
     *
     * @throws LlmException When the value is invalid
     */
    public function resolve(string|array|null $cli = null): array
    {
        if (null !== $cli) {
            return [Selection::fromValue($cli, $this->clis), 'the "cli" argument'];
        }

        $value = PlatformHelper::getEnv(self::ENV_VAR);

        if (false !== $value && '' !== trim($value)) {
            return [Selection::fromValue($value, $this->clis), \sprintf('the %s environment variable', self::ENV_VAR)];
        }

        if (null !== $value = $this->configStorage->get(global: false)) {
            return [Selection::fromValue($value, $this->clis), 'the configuration of the project'];
        }

        if (null !== $value = $this->configStorage->get(global: true)) {
            return [Selection::fromValue($value, $this->clis), 'the global configuration'];
        }

        return [Selection::mode(Selection::MODE_ASK), 'the default'];
    }

    /**
     * @param string|list<string>|null $cli
     *
     * @throws LlmException When no CLI is available, when the call is refused, or when the CLI could not answer
     */
    public function ask(string $prompt, string|array|null $cli = null, ?Context $context = null): string
    {
        $context ??= $this->contextRegistry->getCurrentContext();
        [$selection, $source] = $this->resolve($cli);

        if ($selection->isFixed()) {
            return $this->askWith($selection, $prompt, $context);
        }

        if (null !== $this->runSelection) {
            return $this->askWith($this->runSelection, $prompt, $context);
        }

        if ($selection->isMode(Selection::MODE_CHOOSE)) {
            $this->assertInteractive(\sprintf('Castor cannot let you choose the LLM CLI, the run is not interactive. Set the %s environment variable, or run "castor castor:llm:configure".', self::ENV_VAR));

            $this->runSelection = $this->selector->select($this->io);

            return $this->askWith($this->runSelection, $prompt, $context);
        }

        $installed = $this->clis->getInstalled();

        if (!$installed) {
            throw new LlmException(\sprintf('No LLM CLI found. Install and log in to one of "%s", or set the %s environment variable to the command to run.', implode('", "', $this->clis->getNames()), self::ENV_VAR));
        }

        if ($selection->isMode(Selection::MODE_ASK) && !$this->confirmed) {
            $this->confirm($installed[0], $prompt, $source);
            $this->confirmed = true;
        }

        $failures = [];

        foreach ($installed as $candidate) {
            $candidateSelection = Selection::forCli($candidate);

            try {
                $answer = $this->askWith($candidateSelection, $prompt, $context);
            } catch (LlmException $e) {
                $this->logger->warning(\sprintf('The "%s" LLM CLI could not answer, trying the next one. %s', $candidate->name, $e->getMessage()));
                $failures[] = $e->getMessage();

                continue;
            }

            $this->logger->notice(\sprintf('Using the "%s" LLM CLI.', $candidate->name));
            $this->runSelection = $candidateSelection;

            return $answer;
        }

        throw new LlmException("None of the installed LLM CLIs could answer:\n\n" . implode("\n\n", $failures));
    }

    /**
     * The CLI or command used by the last question.
     */
    public function getLastSelection(): ?Selection
    {
        return $this->lastSelection;
    }

    private function confirm(Cli $cli, string $prompt, string $source): void
    {
        $this->assertInteractive(\sprintf('Castor needs a confirmation before sending a prompt to a LLM (from %s), but the run is not interactive. Set the %s environment variable to "auto", or run "castor castor:llm:configure".', $source, self::ENV_VAR));

        $this->io->writeln(\sprintf('Castor is about to send this prompt to the "%s" LLM CLI:', $cli->name));
        $this->io->newLine();

        foreach ($this->preview($prompt) as $line) {
            $this->io->writeln('    <comment>' . OutputFormatter::escape($line) . '</comment>');
        }

        $this->io->newLine();
        $this->io->writeln(\sprintf('<fg=gray>Run "castor castor:llm:configure", or set the %s environment variable to "auto", to skip this question.</>', self::ENV_VAR));

        if (!$this->io->confirm('Do you want to continue?', true)) {
            throw new LlmException('The LLM call has been refused.');
        }
    }

    /**
     * @return list<string>
     */
    private function preview(string $prompt): array
    {
        $lines = preg_split("/\r?\n/", trim($prompt)) ?: [];
        $preview = array_map(static fn (string $line): string => u($line)->truncate(120, '…')->toString(), \array_slice($lines, 0, self::PREVIEW_LINES));

        if (\count($lines) > self::PREVIEW_LINES) {
            $preview[] = \sprintf('… (%d more lines)', \count($lines) - self::PREVIEW_LINES);
        }

        return $preview;
    }

    private function assertInteractive(string $message): void
    {
        if (!$this->input->isInteractive() || !$this->contextRegistry->getCurrentContext()->supportsInteraction()) {
            throw new LlmException($message);
        }
    }

    private function askWith(Selection $selection, string $prompt, Context $context): string
    {
        if (null !== $selection->cli && !$this->clis->isInstalled($selection->cli)) {
            throw new LlmException(\sprintf('The "%s" LLM CLI is not installed.', $selection->cli->name));
        }

        $this->lastSelection = $selection;

        $name = $selection->getName();
        $command = null !== $selection->cli
            ? $selection->cli->buildCommand($selection->model, $selection->agent, $selection->extraArguments)
            : $selection->customCommand;

        if (null === $command) {
            throw new \LogicException('A selection must have a CLI or a command.');
        }

        $input = $prompt;
        $outputFile = null;

        if (\is_array($command)) {
            if (\in_array(Cli::PROMPT_PLACEHOLDER, $command, true)) {
                $input = null;
            }

            if (\in_array(Cli::OUTPUT_FILE_PLACEHOLDER, $command, true)) {
                $outputFile = $this->fs->tempnam(sys_get_temp_dir(), 'castor-llm-');
            }

            $command = array_map(static fn (string $part): string => match ($part) {
                Cli::PROMPT_PLACEHOLDER => $prompt,
                Cli::OUTPUT_FILE_PLACEHOLDER => (string) $outputFile,
                default => $part,
            }, $command);
        }

        $context = $context
            ->withQuiet()
            ->withAllowFailure()
            ->withInput($input)
            // The verbose arguments are meant for the commands of the project,
            // not for the LLM CLI
            ->withVerboseArguments([])
        ;

        try {
            $process = $this->processRunner->run($command, $context);

            if (!$process->isSuccessful()) {
                throw new LlmException(\sprintf("The \"%s\" LLM CLI failed with exit code %d:\n%s", $name, $process->getExitCode() ?? 0, trim($process->getErrorOutput()) ?: trim($process->getOutput())));
            }

            $answer = null === $outputFile ? $process->getOutput() : (string) file_get_contents($outputFile);
        } finally {
            if (null !== $outputFile) {
                $this->fs->remove($outputFile);
            }
        }

        $answer = trim($answer);

        if ('' === $answer) {
            throw new LlmException(\sprintf('The "%s" LLM CLI returned an empty answer.', $name));
        }

        return $answer;
    }
}
