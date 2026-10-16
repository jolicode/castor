<?php

namespace Castor\Llm;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\LlmException;
use Castor\Helper\PlatformHelper;
use Castor\Runner\ProcessRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * The LLM CLIs Castor knows, and which ones are installed.
 *
 * @internal
 */
class CliRegistry
{
    /**
     * The plan agent of OpenCode may run commands, and the configuration of
     * the user may allow it more: Castor defines its own agent, which may only
     * read the files of the project.
     */
    private const OPENCODE_READ_ONLY_AGENT = 'castor-read-only';

    /** @var list<Cli> */
    private readonly array $clis;

    /** @var array<string, ?string> The path of each CLI already looked up, by name */
    private array $paths = [];

    private readonly ExecutableFinder $executableFinder;

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly ContextRegistry $contextRegistry,
        private readonly string $rootDir,
        private readonly LoggerInterface $logger,
        ?ExecutableFinder $executableFinder = null,
    ) {
        $this->executableFinder = $executableFinder ?? new ExecutableFinder();

        // In the order they are proposed
        $this->clis = [
            new Cli('claude', ['claude', '--print', '--output-format', 'text', '--no-session-persistence', Cli::OPTIONS_PLACEHOLDER], readOnlyArguments: ['--tools', ''], modelOption: '--model', agentOption: '--agent', models: ['fable', 'opus', 'sonnet', 'haiku']),
            new Cli('codex', ['codex', 'exec', '--skip-git-repo-check', '--ephemeral', '--color', 'never', '--output-last-message', Cli::OUTPUT_FILE_PLACEHOLDER, Cli::OPTIONS_PLACEHOLDER, '-'], readOnlyArguments: ['--sandbox', 'read-only'], modelOption: '--model'),
            new Cli('opencode', ['opencode', 'run', Cli::OPTIONS_PLACEHOLDER], modelOption: '--model', agentOption: '--agent', readOnlyAgent: self::OPENCODE_READ_ONLY_AGENT),
            // In a non-interactive run, the model may leave the plan approval mode for the yolo one
            new Cli('gemini', ['gemini', Cli::OPTIONS_PLACEHOLDER], readOnlyArguments: ['--approval-mode', 'plan'], modelOption: '--model', mayModifyProject: true),
            new Cli('copilot', ['copilot', '--silent', Cli::OPTIONS_PLACEHOLDER], modelOption: '--model', agentOption: '--agent'),
            new Cli('cursor-agent', ['cursor-agent', '--print', '--output-format', 'text', Cli::OPTIONS_PLACEHOLDER], readOnlyArguments: ['--mode', 'ask'], modelOption: '--model'),
            // Both approve all the actions of the model in a non-interactive run
            new Cli('amp', ['amp', Cli::OPTIONS_PLACEHOLDER, '--execute'], mayModifyProject: true),
            new Cli('crush', ['crush', 'run', '--quiet', Cli::OPTIONS_PLACEHOLDER], mayModifyProject: true),
            new Cli('qwen', ['qwen', Cli::OPTIONS_PLACEHOLDER], readOnlyArguments: ['--approval-mode', 'plan'], modelOption: '--model'),
            new Cli('llm', ['llm', Cli::OPTIONS_PLACEHOLDER], modelOption: '--model'),
            // "--prompt" without value reads the prompt on the standard input
            new Cli('vibe', ['vibe', '--trust', '--output', 'text', Cli::OPTIONS_PLACEHOLDER, '--prompt'], agentOption: '--agent', readOnlyAgent: 'plan'),
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
     * The CLI proposed when Castor lets the user choose: the first installed
     * one that may not modify the project.
     */
    public function getDefault(): ?Cli
    {
        foreach ($this->getInstalled() as $cli) {
            if (!$cli->mayModifyProject) {
                return $cli;
            }
        }

        return null;
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
     * The environment variables the CLI needs, added to the ones of the context.
     *
     * @return array<string, string>
     *
     * @throws LlmException When the configuration the user gives to the CLI through these variables is invalid
     */
    public function getEnvironment(Cli $cli, ?Context $context = null): array
    {
        return match ($cli->name) {
            'opencode' => ['OPENCODE_CONFIG_CONTENT' => $this->buildOpenCodeConfig($context ?? $this->contextRegistry->getCurrentContext())],
            default => [],
        };
    }

    /**
     * The configuration given to OpenCode through the OPENCODE_CONFIG_CONTENT
     * environment variable, with the one the user may already give this way.
     */
    private function buildOpenCodeConfig(Context $context): string
    {
        $config = new \stdClass();

        // As for the process: the variable of the context wins over the one Castor runs with, and false unsets it
        if ($userConfig = (string) ($context->environment['OPENCODE_CONFIG_CONTENT'] ?? PlatformHelper::getEnv('OPENCODE_CONFIG_CONTENT'))) {
            // Decoded as objects, so that an empty object stays one
            $config = json_decode($userConfig);

            // OpenCode also accepts comments and trailing commas: rather than
            // run it without the configuration of the user, Castor fails
            if (!$config instanceof \stdClass) {
                throw new LlmException('The OPENCODE_CONFIG_CONTENT environment variable must contain a JSON object, without comments nor trailing commas, so that Castor can add its read-only agent to it.');
            }
        }

        $config->agent ??= new \stdClass();
        $config->agent->{self::OPENCODE_READ_ONLY_AGENT} = [
            'mode' => 'primary',
            'description' => 'Reads the files of the project to answer, without modifying them nor running commands.',
            // The last matching rule wins
            'permission' => [
                '*' => 'deny',
                'read' => ['*' => 'allow', '*.env' => 'deny', '*.env.*' => 'deny', '*.env.example' => 'allow'],
                'glob' => 'allow',
                'grep' => 'allow',
                'list' => 'allow',
            ],
        ];

        return json_encode($config, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
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

        try {
            $process = $this->processRunner->run($command, $context);
        } catch (ProcessTimedOutException $e) {
            $this->logger->warning(\sprintf('The command "%s" did not finish within %s seconds, its output is ignored.', implode(' ', $command), $e->getExceededTimeout()), ['exception' => $e]);

            return [];
        }

        if (!$process->isSuccessful()) {
            $this->logger->warning(\sprintf('The command "%s" failed with exit code %d, its output is ignored.', implode(' ', $command), $process->getExitCode() ?? 0));

            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode("\n", $process->getOutput())), static fn (string $line): bool => '' !== $line));
    }
}
