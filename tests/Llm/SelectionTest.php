<?php

namespace Castor\Tests\Llm;

use Castor\Exception\LlmException;
use Castor\Llm\Selection;
use PHPUnit\Framework\Attributes\DataProvider;

class SelectionTest extends LlmTestCase
{
    #[DataProvider('provideModes')]
    public function testAMode(string $value): void
    {
        $selection = Selection::fromValue($value, $this->createCliRegistry());

        $this->assertTrue($selection->isMode(trim($value)));
        $this->assertFalse($selection->isFixed());
        $this->assertSame(trim($value), $selection->toValue());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideModes(): iterable
    {
        yield ['ask'];
        yield ['auto'];
        yield [' choose '];
    }

    public function testAKnownCliWithItsOptions(): void
    {
        $selection = Selection::fromValue('claude --model opus --agent=reviewer --effort high', $this->createCliRegistry());

        $this->assertTrue($selection->isFixed());
        $this->assertSame('claude', $selection->cli?->name);
        $this->assertSame('opus', $selection->model);
        $this->assertSame('reviewer', $selection->agent);
        $this->assertSame(['--effort', 'high'], $selection->extraArguments);
        $this->assertSame('claude --model opus --agent reviewer --effort high', $selection->toValue());
        $this->assertSame('always use the "claude" LLM CLI with the model "opus" with the agent "reviewer" with the arguments "--effort high"', $selection->describe());
    }

    public function testAKnownCliAsAnArray(): void
    {
        $selection = Selection::fromValue(['codex', '--model', 'gpt-5.5'], $this->createCliRegistry());

        $this->assertSame('codex', $selection->cli?->name);
        $this->assertSame('gpt-5.5', $selection->model);
        $this->assertSame('codex --model gpt-5.5', $selection->toValue());
    }

    public function testACustomCommand(): void
    {
        $registry = $this->createCliRegistry();

        $selection = Selection::fromValue('ollama run llama3.2', $registry);
        $this->assertTrue($selection->isFixed());
        $this->assertNull($selection->cli);
        $this->assertSame('ollama run llama3.2', $selection->customCommand);
        $this->assertSame('always run the command "ollama run llama3.2"', $selection->describe());

        $selection = Selection::fromValue(['ollama', 'run', 'llama3.2'], $registry);
        $this->assertSame(['ollama', 'run', 'llama3.2'], $selection->customCommand);
        $this->assertSame('ollama run llama3.2', $selection->toValue());
    }

    #[DataProvider('provideInvalidValues')]
    public function testAnInvalidValue(string|array $value, string $message): void
    {
        $this->expectException(LlmException::class);
        $this->expectExceptionMessage($message);

        Selection::fromValue($value, $this->createCliRegistry());
    }

    /**
     * @return iterable<string, array{string|list<string>, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty' => ['  ', 'The LLM value cannot be empty.'];
        yield 'empty array' => [[], 'The LLM value cannot be empty.'];
        yield 'option without value' => ['claude --model', 'The "--model" option needs a value, in the LLM value "claude --model".'];
        yield 'agent not supported' => ['codex --agent foo', 'The "codex" LLM CLI does not support choosing the agent.'];
        yield 'model not supported' => ['amp --model foo', 'The "amp" LLM CLI does not support choosing the model.'];
    }
}
