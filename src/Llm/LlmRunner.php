<?php

namespace Castor\Llm;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\LlmException;
use Castor\Helper\PlatformHelper;
use Castor\Runner\Parallel\JobRegistry;
use Castor\Runner\ProcessRunner;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

use function Symfony\Component\String\u;

/** @internal */
class LlmRunner
{
    public const ENV_VAR = 'CASTOR_LLM';

    private const PREVIEW_LINES = 5;

    /**
     * The CLI chosen at the first question of the run, when none is set,
     * reused for the next questions.
     */
    private ?Selection $runSelection = null;

    private ?Selection $lastSelection = null;

    public function __construct(
        private readonly CliRegistry $clis,
        private readonly ConfigStorage $configStorage,
        private readonly Selector $selector,
        private readonly ContextRegistry $contextRegistry,
        private readonly ProcessRunner $processRunner,
        private readonly Filesystem $fs,
        private readonly InputInterface $input,
        private readonly SymfonyStyle $io,
        private readonly JobRegistry $jobRegistry,
    ) {
    }

    /**
     * How Castor chooses the LLM CLI, from the "cli" argument, then the
     * CASTOR_LLM environment variable, the configuration of the project, the
     * global configuration, or the default: a question at the first call of
     * the run.
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

        return [Selection::mode(Selection::MODE_CHOOSE), 'the default'];
    }

    /**
     * @param string|list<string>|null $cli
     *
     * @throws LlmException When the CLI cannot be chosen, when the call is refused, or when the CLI could not answer
     */
    public function ask(string $prompt, string|array|null $cli = null, ?Context $context = null): string
    {
        $context ??= $this->contextRegistry->getCurrentContext();
        [$selection, $source] = $this->resolve($cli);

        if ($selection->isFixed()) {
            return $this->askWith($selection, $prompt, $context);
        }

        $this->runSelection ??= $this->choose($prompt, $source, $context);

        return $this->askWith($this->runSelection, $prompt, $context);
    }

    /**
     * The CLI or command used by the last question.
     */
    public function getLastSelection(): ?Selection
    {
        return $this->lastSelection;
    }

    private function choose(string $prompt, string $source, Context $context): Selection
    {
        $this->assertCanAsk($context, \sprintf('Castor needs you to choose the LLM CLI (from %s)', $source), \sprintf('Set the %s environment variable to the CLI to use, or run "castor castor:llm:configure".', self::ENV_VAR));

        $this->io->writeln('Castor is about to send this prompt to a LLM CLI:');
        $this->io->newLine();

        foreach ($this->preview($prompt) as $line) {
            $this->io->writeln('    <comment>' . OutputFormatter::escape($line) . '</comment>');
        }

        $this->io->newLine();
        $this->io->writeln(\sprintf('<fg=gray>Run "castor castor:llm:configure", or set the %s environment variable, to skip these questions.</>', self::ENV_VAR));

        return $this->selector->selectOrRefuse($this->io) ?? throw new LlmException('The LLM call has been refused.');
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

    /**
     * @param string $need   Why Castor needs an answer, as the beginning of a sentence
     * @param string $remedy How to configure Castor so that it does not need one
     */
    private function assertCanAsk(Context $context, string $need, string $remedy): void
    {
        // The output of a job is captured: nobody would see the question
        if ($this->jobRegistry->getCurrent()) {
            throw new LlmException(\sprintf('%s, but a job of parallel() cannot ask a question. %s', $need, $remedy));
        }

        if (!$this->input->isInteractive() || !$context->supportsInteraction()) {
            throw new LlmException(\sprintf('%s, but the run is not interactive. %s', $need, $remedy));
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
            ? $selection->cli->buildReadOnlyCommand($selection->model, $selection->agent, $selection->extraArguments)
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
            ->withEnvironment(null !== $selection->cli ? $this->clis->getEnvironment($selection->cli, $context) : [])
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
        } catch (ProcessTimedOutException $e) {
            throw new LlmException(\sprintf('The "%s" LLM CLI exceeded the timeout of %s seconds of the context.', $name, $e->getExceededTimeout()), previous: $e);
        } catch (ProcessSignaledException $e) {
            throw new LlmException(\sprintf('The "%s" LLM CLI has been stopped by the signal %d.', $name, $e->getSignal()), previous: $e);
        } catch (ProcessStartFailedException $e) {
            // The message ends with the reason, after the whole command line, which contains the prompt when it is an argument
            throw new LlmException(\sprintf('The "%s" LLM CLI could not be started. %s', $name, u($e->getMessage())->afterLast("\n")->toString()), previous: $e);
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
