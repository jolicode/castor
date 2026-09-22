<?php

namespace Castor\Tests\Llm;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Llm\CliRegistry;
use Castor\Llm\ConfigStorage;
use Castor\Runner\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

abstract class LlmTestCase extends TestCase
{
    /** @var list<array{string|list<string>, Context}> The command and the context of each process run */
    protected array $runs = [];

    protected ConfigStorage $configStorage;

    protected function setUp(): void
    {
        $this->configStorage = new ConfigStorage(new ArrayAdapter(), '/project');
        // The agents of Claude Code are looked up in the home directory
        $_SERVER['HOME'] = '/nonexistent';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CASTOR_LLM'], $_SERVER['HOME']);
    }

    /**
     * @param array<string, array{int, string, string}> $results The exit code, output and error output of the processes, by
     *                                                           binary name, or by binary name and first argument
     */
    protected function createProcessRunner(array $results = []): ProcessRunner
    {
        $processRunner = $this->createStub(ProcessRunner::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(function (string|array $command, Context $context) use ($results): Process {
                $this->runs[] = [$command, $context];

                $tokens = \is_array($command) ? $command : explode(' ', $command);
                [$exitCode, $output, $errorOutput] = $results[implode(' ', \array_slice($tokens, 0, 2))] ?? $results[$tokens[0]] ?? [0, "42\n", ''];

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
     * @param list<string>                              $installedClis
     * @param array<string, array{int, string, string}> $results
     */
    protected function createCliRegistry(array $installedClis = [], array $results = [], ?ProcessRunner $processRunner = null): CliRegistry
    {
        $executableFinder = $this->createStub(ExecutableFinder::class);
        $executableFinder
            ->method('find')
            ->willReturnCallback(static fn (string $name): ?string => \in_array($name, $installedClis, true) ? "/usr/bin/{$name}" : null)
        ;

        return new CliRegistry($processRunner ?? $this->createProcessRunner($results), $this->createContextRegistry(), '/project', $executableFinder);
    }

    /**
     * @return list<string|list<string>>
     */
    protected function getRunCommands(): array
    {
        return array_column($this->runs, 0);
    }
}
