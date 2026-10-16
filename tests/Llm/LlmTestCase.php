<?php

namespace Castor\Tests\Llm;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Llm\CliRegistry;
use Castor\Llm\ConfigStorage;
use Castor\Runner\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

abstract class LlmTestCase extends TestCase
{
    /** @var list<array{string|list<string>, Context}> The command and the context of each process run */
    protected array $runs = [];

    protected ConfigStorage $configStorage;

    /** @var array<string, mixed> The environment variables changed by the tests, to restore them */
    private array $previousEnv = [];

    /** @var array<string, string|false> The same, as getenv() returns them */
    private array $previousGetenv = [];

    protected function setUp(): void
    {
        $this->configStorage = new ConfigStorage(new ArrayAdapter(), '/project');

        foreach (['CASTOR_LLM', 'HOME', 'OPENCODE_CONFIG_CONTENT'] as $name) {
            $this->previousEnv[$name] = $_SERVER[$name] ?? null;
        }

        foreach (['CASTOR_LLM', 'OPENCODE_CONFIG_CONTENT'] as $name) {
            unset($_SERVER[$name]);
            // Castor falls back to getenv(): a variable exported in the shell would change the behavior of the tests
            $this->previousGetenv[$name] = getenv($name);
            putenv($name);
        }

        // The agents of Claude Code are looked up in the home directory
        $_SERVER['HOME'] = '/nonexistent';
    }

    /**
     * The environment is restored as it was: the processes run by the next
     * tests inherit it, and Composer needs HOME for instance.
     */
    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $value) {
            if (null === $value) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $value;
            }
        }

        foreach ($this->previousGetenv as $name => $value) {
            if (false !== $value) {
                putenv("{$name}={$value}");
            }
        }
    }

    /**
     * @param array<string, array{int, string, string}|\Throwable> $results The exit code, output and error output of the processes, or
     *                                                                      the exception thrown when running them, by binary name, or by
     *                                                                      binary name and first argument
     */
    protected function createProcessRunner(array $results = []): ProcessRunner
    {
        $processRunner = $this->createStub(ProcessRunner::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(function (string|array $command, Context $context) use ($results): Process {
                $this->runs[] = [$command, $context];

                $tokens = \is_array($command) ? $command : explode(' ', $command);
                $result = $results[implode(' ', \array_slice($tokens, 0, 2))] ?? $results[$tokens[0]] ?? [0, "42\n", ''];

                if ($result instanceof \Throwable) {
                    throw $result;
                }

                [$exitCode, $output, $errorOutput] = $result;

                if (\is_array($command) && false !== ($index = array_search('--output-last-message', $command, true))) {
                    file_put_contents($command[$index + 1], $output);
                    $output = '';
                }

                $process = $this->createStub(Process::class);
                $process->method('isSuccessful')->willReturn(0 === $exitCode);
                $process->method('getExitCode')->willReturn($exitCode);
                $process->method('getOutput')->willReturn($output);
                $process->method('getErrorOutput')->willReturn($errorOutput);

                return $process;
            })
        ;

        return $processRunner;
    }

    protected function createContextRegistry(bool $supportsInteraction = false): ContextRegistry
    {
        $contextRegistry = $this->createStub(ContextRegistry::class);
        $contextRegistry->method('getCurrentContext')->willReturn(new Context(supportsInteraction: $supportsInteraction));

        return $contextRegistry;
    }

    /**
     * @param list<string>                                         $installedClis
     * @param array<string, array{int, string, string}|\Throwable> $results
     */
    protected function createCliRegistry(array $installedClis = [], array $results = [], ?ProcessRunner $processRunner = null): CliRegistry
    {
        $executableFinder = $this->createStub(ExecutableFinder::class);
        $executableFinder
            ->method('find')
            ->willReturnCallback(static fn (string $name): ?string => \in_array($name, $installedClis, true) ? "/usr/bin/{$name}" : null)
        ;

        return new CliRegistry($processRunner ?? $this->createProcessRunner($results), $this->createContextRegistry(), '/project', new NullLogger(), $executableFinder);
    }

    /**
     * @return list<string|list<string>>
     */
    protected function getRunCommands(): array
    {
        return array_column($this->runs, 0);
    }
}
