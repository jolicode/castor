<?php

namespace Castor\Llm;

use Castor\ContextRegistry;
use Castor\Helper\PlatformHelper;
use Castor\Runner\ProcessRunner;
use Symfony\Component\Process\ExecutableFinder;

/**
 * The LLM CLIs Castor knows, and which ones are installed.
 *
 * @internal
 */
class CliRegistry
{
    /** @var list<Cli> */
    private readonly array $clis;

    /** @var array<string, ?string> The path of each CLI already looked up, by name */
    private array $paths = [];

    private readonly ExecutableFinder $executableFinder;

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly ContextRegistry $contextRegistry,
        private readonly string $rootDir,
        ?ExecutableFinder $executableFinder = null,
    ) {
        $this->executableFinder = $executableFinder ?? new ExecutableFinder();

        // In the order they are tried when none is chosen. Each one runs in its
        // read-only mode when it has one, so that the model never modifies the
        // project nor runs commands.
        $this->clis = [
            new Cli('claude', ['claude', '--print', '--output-format', 'text', '--no-session-persistence', '--tools', '', Cli::OPTIONS_PLACEHOLDER], modelOption: '--model', agentOption: '--agent', models: ['fable', 'opus', 'sonnet', 'haiku']),
            new Cli('codex', ['codex', 'exec', '--sandbox', 'read-only', '--skip-git-repo-check', '--ephemeral', '--color', 'never', '--output-last-message', Cli::OUTPUT_FILE_PLACEHOLDER, Cli::OPTIONS_PLACEHOLDER, '-'], modelOption: '--model'),
            new Cli('opencode', ['opencode', 'run', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER], modelOption: '--model', agentOption: '--agent', defaultAgent: 'plan'),
            new Cli('gemini', ['gemini', '--approval-mode', 'plan', Cli::OPTIONS_PLACEHOLDER, '--prompt', Cli::PROMPT_PLACEHOLDER], modelOption: '--model'),
            new Cli('copilot', ['copilot', '--silent', Cli::OPTIONS_PLACEHOLDER, '--prompt', Cli::PROMPT_PLACEHOLDER], modelOption: '--model', agentOption: '--agent'),
            new Cli('cursor-agent', ['cursor-agent', '--print', '--output-format', 'text', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER], modelOption: '--model'),
            new Cli('amp', ['amp', Cli::OPTIONS_PLACEHOLDER, '--execute', Cli::PROMPT_PLACEHOLDER]),
            new Cli('crush', ['crush', 'run', '--quiet', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER]),
            new Cli('qwen', ['qwen', '--approval-mode', 'plan', Cli::OPTIONS_PLACEHOLDER, '--prompt', Cli::PROMPT_PLACEHOLDER], modelOption: '--model'),
            new Cli('llm', ['llm', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER], modelOption: '--model'),
            new Cli('mods', ['mods', '--quiet', Cli::OPTIONS_PLACEHOLDER, Cli::PROMPT_PLACEHOLDER], modelOption: '--model'),
            new Cli('vibe', ['vibe', '--trust', '--output', 'text', Cli::OPTIONS_PLACEHOLDER, '--prompt', Cli::PROMPT_PLACEHOLDER], agentOption: '--agent', defaultAgent: 'plan'),
        ];
    }

    /**
     * @return list<Cli>
     */
    public function all(): array
    {
        return $this->clis;
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_map(static fn (Cli $cli): string => $cli->name, $this->clis);
    }

    public function get(string $name): ?Cli
    {
        foreach ($this->clis as $cli) {
            if ($cli->name === $name) {
                return $cli;
            }
        }

        return null;
    }

    public function getPath(Cli $cli): ?string
    {
        if (!\array_key_exists($cli->name, $this->paths)) {
            $this->paths[$cli->name] = $this->executableFinder->find($cli->getBinary());
        }

        return $this->paths[$cli->name];
    }

    public function isInstalled(Cli $cli): bool
    {
        return null !== $this->getPath($cli);
    }

    /**
     * @return list<Cli>
     */
    public function getInstalled(): array
    {
        return array_values(array_filter($this->clis, $this->isInstalled(...)));
    }

    /**
     * The models the CLI can use, when it is cheap to know them.
     *
     * @return list<string>
     */
    public function listModels(Cli $cli): array
    {
        $models = $cli->models;

        if ('opencode' === $cli->name) {
            $models = [...$models, ...$this->captureLines(['opencode', 'models'])];
        }

        return array_values(array_unique($models));
    }

    /**
     * The agents the CLI can use, when it is cheap to know them.
     *
     * @return list<string>
     */
    public function listAgents(Cli $cli): array
    {
        return match ($cli->name) {
            'opencode' => array_values(array_filter(array_map(
                static fn (string $line): ?string => preg_match('/^(\S+) \(primary\)$/', $line, $matches) ? $matches[1] : null,
                $this->captureLines(['opencode', 'agent', 'list']),
            ))),
            'claude' => $this->listClaudeAgents(),
            default => [],
        };
    }

    /**
     * The agents are Markdown files in the ".claude/agents" directory of the
     * project and of the home directory, named after the agent.
     *
     * @return list<string>
     */
    private function listClaudeAgents(): array
    {
        $directories = [$this->rootDir . '/.claude/agents'];

        if ($home = PlatformHelper::getEnv('HOME')) {
            $directories[] = $home . '/.claude/agents';
        }

        $agents = [];

        foreach ($directories as $directory) {
            foreach (glob($directory . '/*.md') ?: [] as $file) {
                $agents[] = basename($file, '.md');
            }
        }

        return array_values(array_unique($agents));
    }

    /**
     * @param list<string> $command
     *
     * @return list<string> The non-empty lines of the output, or nothing when the command fails
     */
    private function captureLines(array $command): array
    {
        $context = $this->contextRegistry->getCurrentContext()
            ->withQuiet()
            ->withAllowFailure()
            ->withVerboseArguments([])
            ->withTimeout(30)
        ;

        $process = $this->processRunner->run($command, $context);

        if (!$process->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode("\n", $process->getOutput())), static fn (string $line): bool => '' !== $line));
    }
}
