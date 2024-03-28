<?php

namespace Castor\Tests;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Castor\Console\Command\TaskCommand;
use Castor\EventDispatcher;
use Castor\ExpressionLanguage;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Tester\CommandCompletionTester;

class AutocompleteTest extends TaskTestCase
{
    /** @dataProvider getData */
    public function testCompletion(\Closure $function, array $expectedValues, string $input = '')
    {
        $reflectionFunction = new \ReflectionFunction($function);

        $command = new TaskCommand(new AsTask('task'), $reflectionFunction, $this->createMock(EventDispatcher::class), $this->createMock(ExpressionLanguage::class));

        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete([$input]);

        $this->assertSame($expectedValues, $suggestions);
    }

    public function getData(): \Generator
    {
        yield [task_with_suggested_values(...), ['a', 'b', 'c']];
        yield [task_with_autocomplete(...), ['d', 'e', 'f']];
        yield [task_with_autocomplete_filtered(...), ['foo', 'bar', 'baz']];
        yield [task_with_autocomplete_filtered(...), ['bar', 'baz'], 'ba'];
    }
}

function task_with_suggested_values(
    #[AsArgument(name: 'argument', suggestedValues: ['a', 'b', 'c'])]
    string $argument,
): void {
}

function task_with_autocomplete(
    #[AsArgument(name: 'argument', autocomplete: 'Castor\Tests\complete')]
    string $argument,
): void {
}

/** @return string[] */
function complete(CompletionInput $input): array
{
    return [
        'd',
        'e',
        'f',
    ];
}

function task_with_autocomplete_filtered(
    #[AsArgument(name: 'argument', autocomplete: 'Castor\Tests\complete_filtered')]
    string $argument,
): void {
}

/** @return string[] */
function complete_filtered(CompletionInput $input): array
{
    return array_filter([
        'foo',
        'bar',
        'baz',
    ], fn (string $value) => str_starts_with($value, $input->getCompletionValue()));
}
