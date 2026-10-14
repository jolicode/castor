<?php

namespace Castor\Llm;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\LlmException;
use Castor\Helper\PlatformHelper;
use Castor\Runner\Parallel\JobRegistry;
use Castor\Runner\ProcessRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    private const SOURCE_ARGUMENT = 'the "cli" argument';
    private const SOURCE_ENV_VAR = 'the ' . self::ENV_VAR . ' environment variable';

    /**
     * The CLI chosen at the first call of the run, when none is set, reused for
     * the next calls. A CLI and an agent chosen for the questions may not be
     * able to work on the project, and the other way around: there is one
     * choice by read-only mode.
     *
     * @var array<int, Selection>
     */
    private array $runSelections = [];

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
        private readonly LoggerInterface $logger,
        #[Autowire('%repacked%')]
        private readonly bool $repacked,
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
            return [Selection::fromValue($cli, $this->clis), self::SOURCE_ARGUMENT];
        }

        $value = PlatformHelper::getEnv(self::ENV_VAR);

        if (false !== $value && '' !== trim($value)) {
            return [Selection::fromValue($value, $this->clis), self::SOURCE_ENV_VAR];
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
        return $this->send($prompt, $cli, $context, readOnly: true);
    }

    /**
     * Same as ask(), but the CLI may modify the project and run commands.
     *
     * @param string|list<string>|null $cli
     *
     * @throws LlmException When the CLI cannot be chosen or cannot modify the project, when the call is refused, or when the CLI failed
     */
    public function run(string $prompt, string|array|null $cli = null, ?Context $context = null): string
    {
        return $this->send($prompt, $cli, $context, readOnly: false);
    }

    /**
     * The CLI or command used by the last question.
     */
    public function getLastSelection(): ?Selection
    {
        return $this->lastSelection;
    }

    /**
     * @param string|list<string>|null $cli
     */
    private function send(string $prompt, string|array|null $cli, ?Context $context, bool $readOnly): string
    {
        $context ??= $this->contextRegistry->getCurrentContext();
        [$selection, $source] = $this->resolve($cli);

        if ($selection->isFixed()) {
            return $this->sendWith($selection, $prompt, $context, $readOnly);
        }

        $this->runSelections[(int) $readOnly] ??= $this->choose($prompt, $source, $context, $readOnly);

        return $this->sendWith($this->runSelections[(int) $readOnly], $prompt, $context, $readOnly);
    }

    private function choose(string $prompt, string $source, Context $context, bool $readOnly): Selection
    {
        $howToSkip = $this->howToSkipTheChoice($source);
        $this->assertCanAsk($context, \sprintf('Castor needs you to choose the LLM CLI (from %s)', $source), $howToSkip);

        $this->io->writeln($readOnly
            ? 'Castor is about to send this prompt to a LLM CLI:'
            : 'Castor is about to send this prompt to a LLM CLI, which <options=bold>may modify the project and run commands</> to do what it asks:');
        $this->io->newLine();

        foreach ($this->preview($prompt) as $line) {
            $this->io->writeln('    <comment>' . OutputFormatter::escape($line) . '</comment>');
        }

        $this->io->newLine();

        if (null !== $howToSkip) {
            $this->io->writeln(\sprintf('<fg=gray>%s, to skip these questions.</>', $howToSkip));
        }

        $selection = $readOnly ? $this->selector->selectOrRefuse($this->io) : $this->selector->selectWritableOrRefuse($this->io);

        return $selection ?? throw new LlmException('The LLM call has been refused.');
    }

    /**
     * @return list<string>
     */
    private function preview(string $prompt): array
    {
        $lines = preg_split("/\r?\n/", trim($prompt)) ?: [];
        $preview = array_map(static fn (string $line): string => u(self::makeVisible($line))->truncate(120, '…')->toString(), \array_slice($lines, 0, self::PREVIEW_LINES));

        if (\count($lines) > self::PREVIEW_LINES) {
            $preview[] = \sprintf('… (%d more lines)', \count($lines) - self::PREVIEW_LINES);
        }

        return $preview;
    }

    /**
     * The control characters of the prompt would be interpreted by the
     * terminal, to erase the lines around the preview for instance: they are
     * shown as their control picture, and the invalid bytes as "?". A tab
     * only moves the cursor forward: it is shown as spaces, to keep the
     * indented code readable.
     */
    private static function makeVisible(string $line): string
    {
        // @phpstan-ignore return.type (preg_replace_callback() fails on invalid UTF-8 only, which mb_scrub() removes)
        return preg_replace_callback('/\p{Cc}/u', static fn (array $matches): string => match ($code = mb_ord($matches[0])) {
            0x09 => '    ',
            0x7F => "\u{2421}",
            default => $code < 0x20 ? mb_chr(0x2400 + $code) : "\u{FFFD}",
        }, mb_scrub($line, 'UTF-8'));
    }

    /**
     * How to set the CLI, so that Castor does not ask for it: the "cli"
     * argument wins over the environment variable, which wins over the
     * configuration.
     */
    private function howToSkipTheChoice(string $source): ?string
    {
        return match (true) {
            // The task asks for the choice itself
            self::SOURCE_ARGUMENT === $source => null,
            // A repacked application has no castor:llm:configure command
            self::SOURCE_ENV_VAR === $source, $this->repacked => \sprintf('Set the %s environment variable to the CLI to use', self::ENV_VAR),
            default => \sprintf('Set the %s environment variable to the CLI to use, or run "castor castor:llm:configure"', self::ENV_VAR),
        };
    }

    /**
     * @param string  $need      Why Castor needs an answer, as the beginning of a sentence
     * @param ?string $howToSkip How to configure Castor so that it does not need one, when it can be
     */
    private function assertCanAsk(Context $context, string $need, ?string $howToSkip): void
    {
        $end = null !== $howToSkip ? " {$howToSkip}." : '';

        // The output of a job is captured: nobody would see the question
        if ($this->jobRegistry->getCurrent()) {
            throw new LlmException(\sprintf('%s, but a job of parallel() cannot ask a question.%s', $need, $end));
        }

        if (!$this->input->isInteractive() || !$context->supportsInteraction()) {
            throw new LlmException(\sprintf('%s, but the run is not interactive.%s', $need, $end));
        }
    }

    private function sendWith(Selection $selection, string $prompt, Context $context, bool $readOnly): string
    {
        if (null !== $selection->cli && !$this->clis->isInstalled($selection->cli)) {
            throw new LlmException(\sprintf('The "%s" LLM CLI is not installed.', $selection->cli->name));
        }

        $this->lastSelection = $selection;

        $name = $selection->getName();
        $command = match (true) {
            null === $selection->cli => $selection->customCommand,
            $readOnly => $selection->cli->buildReadOnlyCommand($selection->model, $selection->agent, $selection->extraArguments),
            default => $selection->cli->buildWriteCommand($selection->model, $selection->agent, $selection->extraArguments),
        };

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

            // What the CLI did, to check it afterwards
            if ('' !== $errorOutput = trim($process->getErrorOutput())) {
                $this->logger->debug(\sprintf("The \"%s\" LLM CLI printed on its error output:\n%s", $name, $errorOutput));
            }

            if (!$process->isSuccessful()) {
                throw new LlmException(\sprintf("The \"%s\" LLM CLI failed with exit code %d:\n%s", $name, $process->getExitCode() ?? 0, $errorOutput ?: trim($process->getOutput())));
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

        // When the LLM works on the project, the changes matter more than its last words
        if ('' === $answer && $readOnly) {
            throw new LlmException(\sprintf('The "%s" LLM CLI returned an empty answer.', $name));
        }

        return $answer;
    }
}
