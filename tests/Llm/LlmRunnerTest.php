<?php

namespace Castor\Tests\Llm;

use Castor\Context;
use Castor\Exception\LlmException;
use Castor\Llm\LlmRunner;
use Castor\Llm\Selector;
use Castor\Runner\Parallel\Job;
use Castor\Runner\Parallel\JobRegistry;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class LlmRunnerTest extends LlmTestCase
{
    private BufferedOutput $output;

    private JobRegistry $jobRegistry;

    public function testAnExplicitCliIsUsedWithoutAnyQuestion(): void
    {
        $runner = $this->createRunner(['claude', 'gemini']);

        $this->assertSame('42', $runner->ask('What is the answer?', 'gemini'));

        $this->assertSame([['gemini', '--approval-mode', 'plan']], $this->getRunCommands());
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);
        $this->assertSame('', $this->output->fetch());
    }

    /**
     * As an argument, a prompt starting with "-" would be parsed as an option,
     * and a long one could not be passed at all.
     */
    public function testEveryKnownCliGetsThePromptOnItsStandardInput(): void
    {
        $prompt = "- What is the answer?\n- Are you sure?";
        $names = ['claude', 'codex', 'opencode', 'gemini', 'copilot', 'cursor-agent', 'amp', 'crush', 'qwen', 'llm', 'vibe'];
        $runner = $this->createRunner($names);

        foreach ($names as $name) {
            $runner->ask($prompt, $name);
        }

        $this->assertSame($names, array_map(static fn (array $command): string => $command[0], $this->getRunCommands()));

        foreach ($this->runs as [$command, $context]) {
            $this->assertIsArray($command);
            $this->assertNotContains($prompt, $command);
            $this->assertSame($prompt, $context->input, \sprintf('The prompt is written on the standard input of "%s".', $command[0]));
        }
    }

    public function testTheOptionsOfTheCliAreTranslated(): void
    {
        $runner = $this->createRunner(['claude', 'opencode']);

        $runner->ask('What is the answer?', 'claude --model opus --agent reviewer');
        $runner->ask('What is the answer?', 'opencode --agent build --model opencode/big-pickle');

        $this->assertSame([
            ['claude', '--print', '--output-format', 'text', '--no-session-persistence', '--tools', '', '--model', 'opus', '--agent', 'reviewer'],
            ['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'build'],
        ], $this->getRunCommands());
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);
        $this->assertSame('What is the answer?', $this->runs[1][1]->input);
    }

    public function testOpenCodeUsesAnAgentOfCastorThatCanOnlyRead(): void
    {
        $_SERVER['OPENCODE_CONFIG_CONTENT'] = '{"provider":{"ollama":{}},"agent":{"reviewer":{"mode":"primary"}}}';
        $runner = $this->createRunner(['opencode']);

        $runner->ask('What is the answer?', 'opencode');

        [$command, $context] = $this->runs[0];
        $this->assertSame(['opencode', 'run', '--agent', 'castor-read-only'], $command);
        $config = $context->environment['OPENCODE_CONFIG_CONTENT'];
        $this->assertStringContainsString('"provider":{"ollama":{}}', $config, 'The configuration of the user is kept.');
        $this->assertStringContainsString('"reviewer":{"mode":"primary"}', $config);
        $this->assertStringContainsString('"castor-read-only":{"mode":"primary",', $config);
        $this->assertStringContainsString('"permission":{"*":"deny","read":{"*":"allow","*.env":"deny","*.env.*":"deny","*.env.example":"allow"},"glob":"allow","grep":"allow","list":"allow"}', $config);
    }

    public function testOpenCodeKeepsTheConfigurationOfTheContext(): void
    {
        $_SERVER['OPENCODE_CONFIG_CONTENT'] = '{"provider":{"lmstudio":{}}}';
        $runner = $this->createRunner(['opencode']);

        $runner->ask('What is the answer?', 'opencode', new Context(environment: ['OPENCODE_CONFIG_CONTENT' => '{"provider":{"ollama":{}}}']));

        $config = $this->runs[0][1]->environment['OPENCODE_CONFIG_CONTENT'];
        $this->assertStringContainsString('"provider":{"ollama":{}}', $config, 'The variable of the context wins over the one of Castor.');
        $this->assertStringContainsString('"castor-read-only":{"mode":"primary",', $config);
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

    public function testByDefaultTheCliIsChosenOncePerRun(): void
    {
        $runner = $this->createRunner(['claude', 'codex'], [
            'codex' => [0, "The answer is 42.\n", ''],
        ], interactive: true, inputs: "codex\n\n");

        $this->assertSame('The answer is 42.', $runner->ask("What is the answer?\nTo life, the universe and everything."));
        $this->assertSame('The answer is 42.', $runner->ask('Are you sure?'));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Castor is about to send this prompt to a LLM CLI:', $output);
        $this->assertStringContainsString('What is the answer?', $output);
        $this->assertStringContainsString('To life, the universe and everything.', $output);
        $this->assertStringContainsString('Which LLM CLI do you want to use? [claude]', $output);
        $this->assertSame(1, substr_count($output, 'Which LLM CLI do you want to use?'), 'The CLI is chosen once per run.');

        $this->assertSame(['codex', 'codex'], array_map(static fn (array $command): string => $command[0], $this->getRunCommands()));

        // codex writes its answer in a temporary file
        $codexCommand = $this->getRunCommands()[0];
        $this->assertSame('-', end($codexCommand));
        $this->assertStringContainsString('castor-llm-', $codexCommand[array_search('--output-last-message', $codexCommand, true) + 1]);
    }

    public function testALongPromptIsPreviewed(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");

        $runner->ask(implode("\n", array_map(static fn (int $i): string => "Line {$i}", range(1, 8))));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Line 5', $output);
        $this->assertStringNotContainsString('Line 6', $output);
        $this->assertStringContainsString('(3 more lines)', $output);
    }

    public function testThePreviewShowsTheControlCharactersAndTheInvalidBytes(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");

        $runner->ask("Fix this\r\e[2Kline\x07\x7F\u{9B}2J and \xFF\tend");

        $output = $this->output->fetch();
        $this->assertStringContainsString("Fix this\u{240D}\u{241B}[2Kline\u{2407}\u{2421}\u{FFFD}2J and ?    end", $output);
        $this->assertStringNotContainsString("\e", $output);
    }

    public function testTheUserCanRefuseToSendThePrompt(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "None, do not send the prompt\n");

        try {
            $runner->ask('What is the answer?');
            $this->fail('An exception should have been thrown.');
        } catch (LlmException $e) {
            $this->assertSame('The LLM call has been refused.', $e->getMessage());
        }

        $this->assertSame([], $this->runs);
    }

    public function testTheCliCannotBeChosenInANonInteractiveRun(): void
    {
        $runner = $this->createRunner(['claude']);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor needs you to choose the LLM CLI (from the default), but the run is not interactive. Set the CASTOR_LLM environment variable to the CLI to use, or run "castor castor:llm:configure".');

        $runner->ask('What is the answer?');
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
            ['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'build'],
            ['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'build'],
        ], array_values(array_filter($this->getRunCommands(), static fn (array $command): bool => 'run' === ($command[1] ?? null))));
    }

    public function testChooseStillWorksWhenTheCliCannotListItsModelsAndAgents(): void
    {
        $listModels = new Process(['opencode', 'models']);
        $listModels->setTimeout(30);
        $runner = $this->createRunner(['opencode'], [
            'opencode models' => new ProcessTimedOutException($listModels, ProcessTimedOutException::TYPE_GENERAL),
            'opencode agent' => [1, '', 'Error: unknown command'],
        ], interactive: true, inputs: "opencode\nopencode/big-pickle\n\n");
        $_SERVER['CASTOR_LLM'] = 'choose';

        $this->assertSame('42', $runner->ask('What is the answer?'));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Which model? Leave empty for the default of the CLI', $output);
        $this->assertStringContainsString('Which agent? Leave empty for the default of the CLI', $output);
        $commands = $this->getRunCommands();
        $this->assertSame(['opencode', 'run', '--model', 'opencode/big-pickle', '--agent', 'castor-read-only'], end($commands));
    }

    public function testTheCliArgumentCannotBeOverriddenToSkipTheChoice(): void
    {
        $runner = $this->createRunner(['claude']);
        $_SERVER['CASTOR_LLM'] = 'claude';

        try {
            $runner->ask('What is the answer?', 'choose');
            $this->fail('An exception should have been thrown.');
        } catch (LlmException $e) {
            $this->assertSame('Castor needs you to choose the LLM CLI (from the "cli" argument), but the run is not interactive.', $e->getMessage());
        }
    }

    public function testTheEnvironmentVariableAsksToChooseTheCli(): void
    {
        $runner = $this->createRunner(['claude']);
        $_SERVER['CASTOR_LLM'] = 'choose';

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor needs you to choose the LLM CLI (from the CASTOR_LLM environment variable), but the run is not interactive. Set the CASTOR_LLM environment variable to the CLI to use.');

        $runner->ask('What is the answer?');
    }

    public function testARepackedApplicationCannotConfigureTheCli(): void
    {
        $runner = $this->createRunner(['claude'], repacked: true);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor needs you to choose the LLM CLI (from the default), but the run is not interactive. Set the CASTOR_LLM environment variable to the CLI to use.');

        $runner->ask('What is the answer?');
    }

    public function testTheHintToSkipTheQuestionsDependsOnWhereTheChoiceComesFrom(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");
        $runner->ask('What is the answer?');

        $this->assertStringContainsString('Set the CASTOR_LLM environment variable to the CLI to use, or run "castor castor:llm:configure", to skip these questions.', $this->output->fetch());

        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");
        $runner->ask('What is the answer?', 'choose');

        $this->assertStringNotContainsString('to skip these questions', $this->output->fetch());
    }

    public function testChooseCannotRunNonInteractively(): void
    {
        $runner = $this->createRunner(['claude']);
        $this->configStorage->set('choose', global: false);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor needs you to choose the LLM CLI (from the configuration of the project), but the run is not interactive.');

        $runner->ask('What is the answer?');
    }

    public function testTheContextGivenToTheFunctionDecidesWhetherAQuestionCanBeAsked(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Castor needs you to choose the LLM CLI (from the default), but the run is not interactive.');

        $runner->ask('What is the answer?', context: new Context(supportsInteraction: false));
    }

    public function testAJobOfParallelCannotChooseTheCli(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");

        try {
            $this->runInJob(static fn (): string => $runner->ask('What is the answer?'));
            $this->fail('An exception should have been thrown.');
        } catch (LlmException $e) {
            $this->assertSame('Castor needs you to choose the LLM CLI (from the default), but a job of parallel() cannot ask a question. Set the CASTOR_LLM environment variable to the CLI to use, or run "castor castor:llm:configure".', $e->getMessage());
        }

        $this->assertSame('', $this->output->fetch());
        $this->assertSame([], $this->runs);
    }

    public function testAJobOfParallelReusesTheCliChosenBefore(): void
    {
        $runner = $this->createRunner(['claude'], interactive: true, inputs: "\n\n\n");

        $runner->ask('What is the answer?');
        $this->assertSame('42', $this->runInJob(static fn (): string => $runner->ask('Are you sure?')));

        $this->assertSame(1, substr_count($this->output->fetch(), 'Which LLM CLI do you want to use?'));
        $this->assertSame(['claude', 'claude'], array_map(static fn (array $command): string => $command[0], $this->getRunCommands()));
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

    public function testATimeoutIsReported(): void
    {
        $process = new Process(['claude']);
        $process->setTimeout(120);
        $runner = $this->createRunner(['claude'], [
            'claude' => new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL),
        ]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('The "claude" LLM CLI exceeded the timeout of 120 seconds of the context.');

        $runner->ask('What is the answer?', 'claude');
    }

    public function testACliStoppedByASignalIsReported(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('getTermSignal')->willReturn(9);
        $runner = $this->createRunner(['claude'], [
            'claude' => new ProcessSignaledException($process),
        ]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('The "claude" LLM CLI has been stopped by the signal 9.');

        $runner->ask('What is the answer?', 'claude');
    }

    public function testACommandThatCannotBeStartedIsReportedWithoutThePrompt(): void
    {
        $runner = $this->createRunner([], [
            'my-llm' => new ProcessStartFailedException(new Process(['my-llm', 'What is the answer?']), 'proc_open(): posix_spawn() failed: Argument list too long'),
        ]);

        try {
            $runner->ask('What is the answer?', ['my-llm', '{prompt}']);
            $this->fail('An exception should have been thrown.');
        } catch (LlmException $e) {
            $this->assertSame('The "my-llm {prompt}" LLM CLI could not be started. Error: proc_open(): posix_spawn() failed: Argument list too long', $e->getMessage());
        }
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
        $runner = $this->createRunner(interactive: true, inputs: "\nmy-llm --quiet\n");

        $this->assertSame('42', $runner->ask('What is the answer?'));

        $this->assertStringContainsString('None of the LLM CLIs Castor knows is installed: claude, codex,', $this->output->fetch());
        $this->assertSame(['my-llm --quiet'], $this->getRunCommands());
        $this->assertSame('What is the answer?', $this->runs[0][1]->input);
    }

    public function testACliThatMayModifyTheProjectIsNotProposedByDefault(): void
    {
        $runner = $this->createRunner(['gemini', 'amp', 'crush', 'qwen'], interactive: true, inputs: "\n\n");

        $runner->ask('What is the answer?');

        $this->assertStringContainsString('Which LLM CLI do you want to use? [qwen]', $this->output->fetch());
        $this->assertSame('qwen', $this->getRunCommands()[0][0]);

        $this->assertSame('42', $runner->ask('What is the answer?', 'crush'));
        $this->assertSame('crush', $this->getRunCommands()[1][0]);
    }

    public function testTheChosenCliIsNotReplacedByAnotherOneWhenItFails(): void
    {
        $runner = $this->createRunner(['claude', 'codex'], [
            'claude' => [1, '', 'Not logged in · Please run /login'],
        ], interactive: true, inputs: "\n\n\n");

        try {
            $runner->ask('What is the answer?');
            $this->fail('An exception should have been thrown.');
        } catch (LlmException $e) {
            $this->assertSame("The \"claude\" LLM CLI failed with exit code 1:\nNot logged in · Please run /login", $e->getMessage());
        }

        $this->assertSame(['claude'], array_map(static fn (array $command): string => $command[0], $this->getRunCommands()));
    }

    /**
     * @param list<string>                                         $installedClis
     * @param array<string, array{int, string, string}|\Throwable> $results
     */
    private function createRunner(array $installedClis = [], array $results = [], bool $interactive = false, string $inputs = '', bool $repacked = false): LlmRunner
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
        $this->jobRegistry = new JobRegistry($input);
        $this->runs = [];

        return new LlmRunner(
            $clis,
            $this->configStorage,
            new Selector($clis),
            $this->createContextRegistry($interactive),
            $processRunner,
            new Filesystem(),
            $input,
            new SymfonyStyle($input, $this->output),
            $this->jobRegistry,
            $repacked,
        );
    }

    /**
     * Runs the callback as a job of parallel() does: in a fiber attached to a job.
     *
     * @param \Closure(): string $callback
     */
    private function runInJob(\Closure $callback): string
    {
        $fiber = new \Fiber($callback);
        $this->jobRegistry->attach($fiber, new Job('job', new NullOutput(), new NullOutput(), static function (): void {}, static function (): void {}));
        $fiber->start();

        return $fiber->getReturn();
    }
}
