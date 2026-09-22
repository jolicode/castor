<?php

namespace Castor\Tests\Llm;

use Castor\Context;
use Castor\Exception\LlmException;
use Castor\Llm\LlmRunner;
use Castor\Llm\Selector;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class LlmRunnerTest extends LlmTestCase
{
    private BufferedOutput $output;

    public function testAnExplicitCliIsUsedWithoutConfirmation(): void
    {
        $runner = $this->createRunner(['claude', 'gemini']);

        $this->assertSame('42', $runner->ask('What is the answer?', 'gemini'));

        $this->assertSame([['gemini', '--approval-mode', 'plan', '--prompt', 'What is the answer?']], $this->getRunCommands());
        $this->assertNull($this->runs[0][1]->input);
        $this->assertSame('', $this->output->fetch());
    }

    public function testTheOptionsOfTheCliAreTranslated(): void
    {
        $runner = $this->createRunner(['claude', 'opencode']);

        $runner->ask('What is the answer?', 'claude --model opus --agent reviewer');
        $runner->ask('What is the answer?', 'opencode --agent build --model opencode/big-pickle');

        $this->assertSame([
            ['claude', '--print', '--output-format', 'text', '--no-session-persistence', '--tools', '', '--model', 'opus', '--agent', 'reviewer'],
            ['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'build', 'What is the answer?'],
        ], $this->getRunCommands());
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);
    }

    public function testTheEnvironmentVariableWinsOverTheConfiguration(): void
    {
        $runner = $this->createRunner(['claude', 'codex', 'gemini']);
        $this->configStorage->set('claude', global: false);
        $this->configStorage->set('gemini', global: true);
        $_SERVER['CASTOR_LLM'] = 'codex';

        $runner->ask('What is the answer?');

        $this->assertSame('codex', $this->getRunCommands()[0][0]);
    }

    public function testTheConfigurationOfTheProjectWinsOverTheGlobalOne(): void
    {
        $runner = $this->createRunner(['claude', 'codex', 'gemini']);
        $this->configStorage->set('gemini', global: false);
        $this->configStorage->set('codex', global: true);

        $runner->ask('What is the answer?');

        $this->assertSame('gemini', $this->getRunCommands()[0][0]);

        $this->configStorage->remove(global: false);
        $this->runs = [];

        $runner->ask('What is the answer?');

        $this->assertSame('codex', $this->getRunCommands()[0][0]);
    }

    public function testByDefaultAConfirmationIsAskedThenTheFirstCliThatAnswersIsUsed(): void
    {
        $runner = $this->createRunner(['claude', 'codex', 'opencode'], [
            'claude' => [0, "\n", ''],
            'codex' => [1, '', 'ERROR: 401 Unauthorized'],
            'opencode' => [0, "The answer is 42.\n", ''],
        ], interactive: true, inputs: "yes\n");

        $this->assertSame('The answer is 42.', $runner->ask("What is the answer?\nTo life, the universe and everything."));
        $this->assertSame('The answer is 42.', $runner->ask('Are you sure?'));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Castor is about to send this prompt to the "claude" LLM CLI:', $output);
        $this->assertStringContainsString('What is the answer?', $output);
        $this->assertStringContainsString('To life, the universe and everything.', $output);
        $this->assertStringContainsString('Do you want to continue? (yes/no) [yes]:', $output);
        $this->assertSame(1, substr_count($output, 'Do you want to continue?'), 'The confirmation is asked once per run.');

        // The CLI that answered is reused for the next question
        $this->assertSame(['claude', 'codex', 'opencode', 'opencode'], array_map(static fn (array $command): string => $command[0], $this->getRunCommands()));

        // codex writes its answer in a temporary file
        $codexCommand = $this->getRunCommands()[1];
        $this->assertSame('-', end($codexCommand));
        $this->assertStringContainsString('castor-llm-', $codexCommand[array_search('--output-last-message', $codexCommand, true) + 1]);
    }

    public function testALongPromptIsPreviewed(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "yes\n");

        $runner->ask(implode("\n", array_map(static fn (int $i): string => "Line {$i}", range(1, 8))));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Line 5', $output);
        $this->assertStringNotContainsString('Line 6', $output);
        $this->assertStringContainsString('(3 more lines)', $output);
    }

    public function testARefusedConfirmationThrows(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "no\n");

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('The LLM call has been refused.');

        $runner->ask('What is the answer?');
    }

    public function testTheConfirmationCannotBeAskedInANonInteractiveRun(): void
    {
        $runner = $this->createRunner(['claude']);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor needs a confirmation before sending a prompt to a LLM (from the default), but the run is not interactive. Set the CASTOR_LLM environment variable to "auto", or run "castor castor:llm:configure".');

        $runner->ask('What is the answer?');
    }

    public function testAutoDoesNotAsk(): void
    {
        $runner = $this->createRunner(['claude']);
        $this->configStorage->set('auto', global: true);

        $this->assertSame('42', $runner->ask('What is the answer?'));
        $this->assertSame('', $this->output->fetch());
    }

    public function testChooseLetsTheUserPickTheCliTheModelAndTheAgent(): void
    {
        $runner = $this->createRunner(['claude', 'opencode'], [
            'opencode models' => [0, "opencode/big-pickle\nopencode/other\n", ''],
            'opencode agent list' => [0, "build (primary)\nexplore (subagent)\nplan (primary)\n", ''],
        ], interactive: true, inputs: "opencode\nopencode/big-pickle\nbuild\n");
        $_SERVER['CASTOR_LLM'] = 'choose';

        $this->assertSame('42', $runner->ask('What is the answer?'));
        $this->assertSame('42', $runner->ask('Are you sure?'));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Which LLM CLI do you want to use?', $output);
        $this->assertStringContainsString('Which model?', $output);
        $this->assertStringContainsString('Which agent?', $output);
        $this->assertStringNotContainsString('explore', $output, 'Only the primary agents are proposed.');
        $this->assertSame(1, substr_count($output, 'Which LLM CLI do you want to use?'), 'The choice is asked once per run.');

        $this->assertSame([
            ['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'build', 'What is the answer?'],
            ['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'build', 'Are you sure?'],
        ], array_values(array_filter($this->getRunCommands(), static fn (array $command): bool => 'run' === ($command[1] ?? null))));
    }

    public function testChooseCannotRunNonInteractively(): void
    {
        $runner = $this->createRunner(['claude']);
        $this->configStorage->set('choose', global: false);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor cannot let you choose the LLM CLI, the run is not interactive.');

        $runner->ask('What is the answer?');
    }

    public function testTheCliRunsQuietlyWithTheContextOfTheTask(): void
    {
        $runner = $this->createRunner(['claude']);

        $runner->ask('What is the answer?', 'claude', new Context(environment: ['FOO' => 'bar'], verboseArguments: ['-vvv']));

        $context = $this->runs[0][1];
        $this->assertSame(['FOO' => 'bar'], $context->environment);
        $this->assertTrue($context->quiet);
        $this->assertTrue($context->allowFailure);
        $this->assertSame([], $context->verboseArguments);
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
        $runner = $this->createRunner();

        $runner->ask('What is the answer?', ['ollama', 'run', 'llama3.2']);
        $runner->ask('What is the answer?', 'ollama run llama3.2');

        $this->assertSame([['ollama', 'run', 'llama3.2'], 'ollama run llama3.2'], $this->getRunCommands());
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);
        $this->assertSame('What is the answer?', $this->runs[1][1]->input);
    }

    public function testACustomCommandCanTakeThePromptAsAnArgument(): void
    {
        $runner = $this->createRunner();

        $runner->ask('What is the answer?', ['my-llm', '--prompt', '{prompt}']);

        $this->assertSame([['my-llm', '--prompt', 'What is the answer?']], $this->getRunCommands());
        $this->assertNull($this->runs[0][1]->input);
    }

    public function testNoCliInstalled(): void
    {
        $runner = $this->createRunner();
        $_SERVER['CASTOR_LLM'] = 'auto';

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
        $_SERVER['CASTOR_LLM'] = 'auto';

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
     * @param array<string, array{int, string, string}> $results
     */
    private function createRunner(array $installedClis = [], array $results = [], bool $interactive = false, string $inputs = ''): LlmRunner
    {
        $processRunner = $this->createProcessRunner($results);
        $clis = $this->createCliRegistry($installedClis, processRunner: $processRunner);

        $input = new ArrayInput([]);
        $input->setInteractive($interactive);
        $stream = fopen('php://memory', 'r+') ?: throw new \RuntimeException('Could not open a memory stream.');
        fwrite($stream, $inputs);
        rewind($stream);
        $input->setStream($stream);

        $this->output = new BufferedOutput();
        $this->runs = [];

        return new LlmRunner(
            $clis,
            $this->configStorage,
            new Selector($clis),
            $this->createContextRegistry($interactive),
            $processRunner,
            new Filesystem(),
            new NullLogger(),
            $input,
            new SymfonyStyle($input, $this->output),
        );
    }
}
