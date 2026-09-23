<?php

namespace Castor\Tests\Llm;

use Castor\Console\Command\LlmConfigureCommand;
use Castor\Console\Command\LlmDebugCommand;
use Castor\Llm\LlmRunner;
use Castor\Llm\Selector;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class LlmCommandsTest extends LlmTestCase
{
    public function testConfigureWithAValue(): void
    {
        $tester = $this->createConfigureTester(['claude']);

        $tester->execute(['value' => 'claude --model opus', '--global' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertDisplayContains('Castor will always use the "claude" LLM CLI with the model "opus", for all your projects.', $tester->getDisplay());
        $this->assertDisplayContains('set the CASTOR_LLM environment variable to "claude --model opus"', $tester->getDisplay());
        $this->assertSame('claude --model opus', $this->configStorage->get(global: true));
        $this->assertNull($this->configStorage->get(global: false));
    }

    public function testConfigureWithAnInvalidValue(): void
    {
        $tester = $this->createConfigureTester(['claude']);

        $tester->execute(['value' => 'codex --agent foo']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertDisplayContains('The "codex" LLM CLI does not support choosing the agent.', $tester->getDisplay());
        $this->assertNull($this->configStorage->get(global: false));
    }

    public function testConfigureWarnsWhenACustomCommandIsNotFound(): void
    {
        $tester = $this->createConfigureTester();

        $tester->execute(['value' => 'i-do-not-exist run llama3.2']);

        $tester->assertCommandIsSuccessful();
        $this->assertDisplayContains('The "i-do-not-exist" executable was not found in the PATH.', $tester->getDisplay());
        $this->assertSame('i-do-not-exist run llama3.2', $this->configStorage->get(global: false));
    }

    public function testConfigureInteractively(): void
    {
        $tester = $this->createConfigureTester(['claude', 'opencode']);
        $tester->setInputs(['fixed', 'claude', 'haiku', '', 'this project']);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertDisplayContains('When a task asks a LLM, Castor should', $display);
        $this->assertDisplayContains('Which LLM CLI do you want to use?', $display);
        $this->assertDisplayContains('Which model?', $display);
        $this->assertDisplayContains('Which agent? Leave empty for the default of the CLI', $display);
        $this->assertDisplayContains('Save this configuration for', $display);
        $this->assertDisplayContains('Castor will always use the "claude" LLM CLI with the model "haiku", for this project.', $display);
        $this->assertSame('claude --model haiku', $this->configStorage->get(global: false));
    }

    public function testConfigureAModeInteractively(): void
    {
        $tester = $this->createConfigureTester(['claude']);
        $tester->setInputs(['auto']);

        $tester->execute(['--global' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertDisplayNotContains('Save this configuration for', $tester->getDisplay());
        $this->assertSame('auto', $this->configStorage->get(global: true));
    }

    public function testConfigureNeedsAValueInANonInteractiveRun(): void
    {
        $tester = $this->createConfigureTester(['claude']);

        $tester->execute([], ['interactive' => false]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertDisplayContains('The run is not interactive: pass the value as an argument.', $tester->getDisplay());
    }

    public function testConfigureReset(): void
    {
        $this->configStorage->set('auto', global: false);
        $tester = $this->createConfigureTester();

        $tester->execute(['--reset' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertDisplayContains('The LLM configuration of this project has been removed.', $tester->getDisplay());
        $this->assertNull($this->configStorage->get(global: false));
    }

    public function testDebug(): void
    {
        $this->configStorage->set('auto', global: false);
        $this->configStorage->set('choose', global: true);
        $tester = $this->createDebugTester(['claude', 'opencode']);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertDisplayContains('claude installed (/usr/bin/claude)', $display);
        $this->assertDisplayContains('codex not installed', $display);
        $this->assertDisplayContains('CASTOR_LLM environment variable not set', $display);
        $this->assertDisplayContains('Configuration of the project auto', $display);
        $this->assertDisplayContains('Global configuration choose', $display);
        $this->assertDisplayContains('Castor will use the first LLM CLI installed, without asking, from the configuration of the project.', $display);
        $this->assertDisplayContains('The CLIs are tried in this order: claude, opencode.', $display);
    }

    public function testDebugShowsTheCommandOfAFixedSelection(): void
    {
        $_SERVER['CASTOR_LLM'] = 'claude --model opus';
        $tester = $this->createDebugTester(['claude']);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertDisplayContains('Castor will always use the "claude" LLM CLI with the model "opus", from the CASTOR_LLM environment variable.', $display);
        $this->assertDisplayContains("The command is 'claude' '--print' '--output-format' 'text' '--no-session-persistence' '--tools' \"\" '--model' 'opus', with the prompt on its standard input.", $display);
    }

    public function testDebugWithATest(): void
    {
        $_SERVER['CASTOR_LLM'] = 'auto';
        $tester = $this->createDebugTester(['claude'], ['claude' => [0, "I am a fake model.\n", '']]);

        $tester->execute(['--test' => true]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertDisplayContains('I am a fake model.', $display);
        $this->assertDisplayContains('"claude" answered in', $display);
        $this->assertSame('Which LLM model are you? Answer in one short sentence.', $this->runs[0][1]->input);
    }

    /**
     * The blocks of SymfonyStyle wrap the messages on several lines.
     */
    private function assertDisplayContains(string $expected, string $display): void
    {
        $this->assertStringContainsString($expected, $this->normalize($display));
    }

    private function assertDisplayNotContains(string $expected, string $display): void
    {
        $this->assertStringNotContainsString($expected, $this->normalize($display));
    }

    private function normalize(string $display): string
    {
        return preg_replace(['/^ [!]?\s*/m', '/\s+/'], ['', ' '], $display) ?? $display;
    }

    /**
     * @param list<string> $installedClis
     */
    private function createConfigureTester(array $installedClis = []): CommandTester
    {
        $clis = $this->createCliRegistry($installedClis);

        return new CommandTester(new Application()->addCommand(new LlmConfigureCommand($clis, $this->configStorage, new Selector($clis))) ?? throw new \LogicException('The command could not be added.'));
    }

    /**
     * @param list<string>                              $installedClis
     * @param array<string, array{int, string, string}> $results
     */
    private function createDebugTester(array $installedClis = [], array $results = []): CommandTester
    {
        $processRunner = $this->createProcessRunner($results);
        $clis = $this->createCliRegistry($installedClis, processRunner: $processRunner);
        $input = new ArrayInput([]);
        $runner = new LlmRunner($clis, $this->configStorage, new Selector($clis), $this->createContextRegistry(), $processRunner, new Filesystem(), new NullLogger(), $input, new SymfonyStyle($input, new NullOutput()));

        return new CommandTester(new Application()->addCommand(new LlmDebugCommand($clis, $this->configStorage, $runner)) ?? throw new \LogicException('The command could not be added.'));
    }
}
