<?php

namespace Castor\Tests;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\LlmException;
use Castor\Runner\LlmRunner;
use Castor\Runner\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LlmRunnerTest extends TestCase
{
    /** @var list<array{string|list<string>, Context}> The command and the context of each process run */
    private array $runs = [];

    protected function tearDown(): void
    {
        unset($_SERVER['CASTOR_LLM_CLI']);
    }

    public function testTheFirstInstalledCliThatAnswersIsUsed(): void
    {
        $runner = $this->createRunner(['claude', 'codex', 'opencode', 'gemini'], [
            'claude' => [0, "\n", ''],
            'codex' => [1, '', 'ERROR: 401 Unauthorized'],
            'opencode' => [0, "The answer is 42.\n", ''],
        ]);

        $this->assertSame('The answer is 42.', $runner->ask('What is the answer?'));

        // gemini is installed but never needed
        $this->assertSame(['claude', 'codex', 'opencode'], array_map(static fn (array $run): string => $run[0][0], $this->runs));

        // claude reads the prompt on its stdin
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);

        // codex writes its answer in a temporary file
        $codexCommand = $this->runs[1][0];
        $this->assertSame('-', end($codexCommand));
        $this->assertStringContainsString('castor-llm-', $codexCommand[array_search('--output-last-message', $codexCommand, true) + 1]);

        // opencode takes the prompt as an argument
        $this->assertSame(['opencode', 'run', '--agent', 'plan', 'What is the answer?'], $this->runs[2][0]);
        $this->assertNull($this->runs[2][1]->input);
    }

    public function testTheCliThatAnsweredIsReusedForTheNextQuestions(): void
    {
        $runner = $this->createRunner(['claude', 'codex'], [
            'claude' => [1, '', 'Not logged in'],
            'codex' => [0, '42', ''],
        ]);

        $runner->ask('What is the answer?');
        $runner->ask('Are you sure?');

        $this->assertSame(['claude', 'codex', 'codex'], array_map(static fn (array $run): string => $run[0][0], $this->runs));
    }

    public function testTheCliRunsQuietlyWithTheContextOfTheTask(): void
    {
        $runner = $this->createRunner(['claude']);

        $context = new Context(environment: ['FOO' => 'bar'], verboseArguments: ['-vvv']);

        $runner->ask('What is the answer?', context: $context);

        $context = $this->runs[0][1];
        $this->assertSame(['FOO' => 'bar'], $context->environment);
        $this->assertTrue($context->quiet);
        $this->assertTrue($context->allowFailure);
        $this->assertSame([], $context->verboseArguments);
    }

    public function testTheCliCanBeChosen(): void
    {
        $runner = $this->createRunner(['claude', 'gemini']);

        $runner->ask('What is the answer?', 'gemini');

        $this->assertSame([['gemini', '--approval-mode', 'plan', '--prompt', 'What is the answer?']], array_column($this->runs, 0));
    }

    public function testTheChosenCliMustBeInstalled(): void
    {
        $runner = $this->createRunner(['claude']);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('The "gemini" LLM CLI is not installed.');

        $runner->ask('What is the answer?', 'gemini');
    }

    public function testTheFailureOfTheChosenCliIsReported(): void
    {
        $runner = $this->createRunner(['claude', 'codex'], [
            'claude' => [1, '', 'Not logged in · Please run /login'],
        ]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage("The \"claude\" LLM CLI failed with exit code 1:\nNot logged in · Please run /login");

        $runner->ask('What is the answer?', 'claude');
    }

    public function testACustomCommandGetsThePromptOnItsStandardInput(): void
    {
        $runner = $this->createRunner([]);

        $runner->ask('What is the answer?', ['ollama', 'run', 'llama3.2']);
        $runner->ask('What is the answer?', 'ollama run llama3.2');

        $this->assertSame([['ollama', 'run', 'llama3.2'], 'ollama run llama3.2'], array_column($this->runs, 0));
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);
        $this->assertSame('What is the answer?', $this->runs[1][1]->input);
    }

    public function testACustomCommandCanTakeThePromptAsAnArgument(): void
    {
        $runner = $this->createRunner([]);

        $runner->ask('What is the answer?', ['gemini', '--model', 'gemini-2.5-flash', '--prompt', '{prompt}']);

        $this->assertSame([['gemini', '--model', 'gemini-2.5-flash', '--prompt', 'What is the answer?']], array_column($this->runs, 0));
        $this->assertNull($this->runs[0][1]->input);
    }

    public function testTheCliCanBeChosenWithAnEnvironmentVariable(): void
    {
        $runner = $this->createRunner(['claude', 'codex']);

        $_SERVER['CASTOR_LLM_CLI'] = 'codex';
        $runner->ask('What is the answer?');

        $_SERVER['CASTOR_LLM_CLI'] = 'ollama run llama3.2';
        $runner->ask('What is the answer?');

        $this->assertSame('codex', $this->runs[0][0][0]);
        $this->assertSame('ollama run llama3.2', $this->runs[1][0]);
    }

    public function testNoCliInstalled(): void
    {
        $runner = $this->createRunner([]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('No LLM CLI found. Install and log in to one of "claude", "codex", "opencode"');

        $runner->ask('What is the answer?');
    }

    public function testNoCliCouldAnswer(): void
    {
        $runner = $this->createRunner(['claude', 'codex'], [
            'claude' => [1, '', 'Not logged in · Please run /login'],
            'codex' => [1, '', 'ERROR: 401 Unauthorized'],
        ]);

        try {
            $runner->ask('What is the answer?');
            $this->fail('An exception should have been thrown.');
        } catch (LlmException $e) {
            $this->assertStringStartsWith('None of the installed LLM CLIs could answer:', $e->getMessage());
            $this->assertStringContainsString("The \"claude\" LLM CLI failed with exit code 1:\nNot logged in · Please run /login", $e->getMessage());
            $this->assertStringContainsString("The \"codex\" LLM CLI failed with exit code 1:\nERROR: 401 Unauthorized", $e->getMessage());
        }
    }

    /**
     * @param list<string>                              $installedClis
     * @param array<string, array{int, string, string}> $results       The exit code, output and error output of each CLI, by binary name
     */
    private function createRunner(array $installedClis, array $results = []): LlmRunner
    {
        $processRunner = $this->createStub(ProcessRunner::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(function (string|array $command, Context $context) use ($results): Process {
                $this->runs[] = [$command, $context];

                $binary = \is_array($command) ? $command[0] : explode(' ', $command)[0];
                [$exitCode, $output, $errorOutput] = $results[$binary] ?? [0, "42\n", ''];

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

        $executableFinder = $this->createStub(ExecutableFinder::class);
        $executableFinder
            ->method('find')
            ->willReturnCallback(static fn (string $name): ?string => \in_array($name, $installedClis, true) ? "/usr/bin/{$name}" : null)
        ;

        $contextRegistry = $this->createStub(ContextRegistry::class);
        $contextRegistry->method('getCurrentContext')->willReturn(new Context());

        return new LlmRunner($contextRegistry, $processRunner, new Filesystem(), new NullLogger(), $executableFinder);
    }
}
