<?php

namespace Castor\Runner;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\LlmException;
use Castor\Helper\PlatformHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;

/** @internal */
class LlmRunner
{
    /**
     * The CLIs Castor knows how to run non-interactively, tried in this order
     * when none is chosen explicitly.
     *
     * The prompt is written on the standard input of the CLI, unless the
     * command has a "{prompt}" argument, which is replaced by the prompt. The
     * answer is read on its standard output, unless the command has an
     * "{output_file}" argument, which is replaced by the path of a temporary
     * file the CLI writes its answer to.
     *
     * @var array<string, list<string>>
     */
    private const CLIS = [
        'claude' => ['claude', '--print', '--output-format', 'text', '--tools', '', '--no-session-persistence'],
        'codex' => ['codex', 'exec', '--sandbox', 'read-only', '--skip-git-repo-check', '--ephemeral', '--color', 'never', '--output-last-message', '{output_file}', '-'],
        'opencode' => ['opencode', 'run', '--agent', 'plan', '{prompt}'],
        'gemini' => ['gemini', '--approval-mode', 'plan', '--prompt', '{prompt}'],
        'copilot' => ['copilot', '--silent', '--prompt', '{prompt}'],
        'cursor-agent' => ['cursor-agent', '--print', '--output-format', 'text', '{prompt}'],
        'amp' => ['amp', '--execute', '{prompt}'],
        'crush' => ['crush', 'run', '--quiet', '{prompt}'],
        'qwen' => ['qwen', '--approval-mode', 'plan', '--prompt', '{prompt}'],
        'llm' => ['llm', '{prompt}'],
        'mods' => ['mods', '--quiet', '{prompt}'],
    ];

    private const PROMPT_PLACEHOLDER = '{prompt}';
    private const OUTPUT_FILE_PLACEHOLDER = '{output_file}';

    /**
     * The CLI that answered a previous question, reused for the next ones.
     *
     * @var array{string, list<string>}|null
     */
    private ?array $detectedCli = null;

    private readonly ExecutableFinder $executableFinder;

    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        private readonly ProcessRunner $processRunner,
        private readonly Filesystem $fs,
        private readonly LoggerInterface $logger,
        ?ExecutableFinder $executableFinder = null,
    ) {
        $this->executableFinder = $executableFinder ?? new ExecutableFinder();
    }

    /**
     * @return list<string>
     */
    public static function getKnownClis(): array
    {
        return array_keys(self::CLIS);
    }

    /**
     * @param string|list<string>|null $cli The name of a known CLI, or a custom command. When null, the
     *                                      CASTOR_LLM_CLI environment variable is used, then the first
     *                                      installed CLI that answers
     *
     * @throws LlmException When no CLI is available, or when it could not answer
     */
    public function ask(string $prompt, string|array|null $cli = null, ?Context $context = null): string
    {
        $context ??= $this->contextRegistry->getCurrentContext();
        $cli ??= PlatformHelper::getEnv('CASTOR_LLM_CLI') ?: null;

        if (null !== $cli) {
            [$name, $command] = $this->resolveCli($cli);

            return $this->askWith($name, $command, $prompt, $context);
        }

        if (null !== $this->detectedCli) {
            [$name, $command] = $this->detectedCli;

            return $this->askWith($name, $command, $prompt, $context);
        }

        $installedClis = array_filter(self::CLIS, fn (array $command): bool => null !== $this->executableFinder->find($command[0]));

        if (!$installedClis) {
            throw new LlmException(\sprintf('No LLM CLI found. Install and log in to one of "%s", or set the CASTOR_LLM_CLI environment variable to the command to run.', implode('", "', array_keys(self::CLIS))));
        }

        $failures = [];

        foreach ($installedClis as $name => $command) {
            try {
                $answer = $this->askWith($name, $command, $prompt, $context);
            } catch (LlmException $e) {
                $this->logger->warning(\sprintf('The "%s" LLM CLI could not answer, trying the next one. %s', $name, $e->getMessage()));
                $failures[] = $e->getMessage();

                continue;
            }

            $this->logger->notice(\sprintf('Using the "%s" LLM CLI.', $name));
            $this->detectedCli = [$name, $command];

            return $answer;
        }

        throw new LlmException("None of the installed LLM CLIs could answer:\n\n" . implode("\n\n", $failures));
    }

    /**
     * @param string|list<string> $cli
     *
     * @return array{string, string|list<string>}
     */
    private function resolveCli(string|array $cli): array
    {
        if (\is_string($cli) && isset(self::CLIS[$cli])) {
            $command = self::CLIS[$cli];

            if (null === $this->executableFinder->find($command[0])) {
                throw new LlmException(\sprintf('The "%s" LLM CLI is not installed.', $cli));
            }

            return [$cli, $command];
        }

        return [\is_array($cli) ? implode(' ', $cli) : $cli, $cli];
    }

    /**
     * @param string|list<string> $command
     */
    private function askWith(string $name, string|array $command, string $prompt, Context $context): string
    {
        $input = $prompt;
        $outputFile = null;

        if (\is_array($command)) {
            if (\in_array(self::PROMPT_PLACEHOLDER, $command, true)) {
                $input = null;
            }

            if (\in_array(self::OUTPUT_FILE_PLACEHOLDER, $command, true)) {
                $outputFile = $this->fs->tempnam(sys_get_temp_dir(), 'castor-llm-');
            }

            $command = array_map(static fn (string $part): string => match ($part) {
                self::PROMPT_PLACEHOLDER => $prompt,
                self::OUTPUT_FILE_PLACEHOLDER => (string) $outputFile,
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
