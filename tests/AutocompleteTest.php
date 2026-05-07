<?php

namespace Castor\Tests;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsPathArgument;
use Castor\Attribute\AsPathOption;
use Castor\Attribute\AsTask;
use Castor\Console\Command\TaskCommand;
use Castor\ContextRegistry;
use Castor\Descriptor\TaskDescriptor;
use Castor\ExpressionLanguage;
use Castor\Helper\Slugger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;

class AutocompleteTest extends TaskTestCase
{
    #[DataProvider('provideCompletionTests')]
    public function testCompletion(\Closure $function, array $expectedValues, string $input = ''): void
    {
        $descriptor = new TaskDescriptor(new AsTask('task'), new \ReflectionFunction($function));

        $command = new TaskCommand(
            $descriptor,
            $this->createMock(ExpressionLanguage::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ContextRegistry::class),
            new Slugger(new AsciiSlugger()),
            new Filesystem(),
            new FileLinkFormatter(),
        );

        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete([$input]);

        $this->assertSame($expectedValues, $suggestions);
    }

    public static function provideCompletionTests(): \Generator
    {
        yield [task_with_static_autocomplete(...), ['a', 'b', 'c']];
        yield [task_with_autocomplete(...), ['d', 'e', 'f']];
        yield [task_with_autocomplete_filtered(...), ['foo', 'bar', 'baz']];
        yield [task_with_autocomplete_filtered(...), ['bar', 'baz'], 'ba'];
    }

    #[DataProvider('providePathCompletionTests')]
    public function testPathCompletion(\Closure $function, array $input, array $expectedItems, bool $exactExpectations = false): void
    {
        $descriptor = new TaskDescriptor(new AsTask('task'), new \ReflectionFunction($function));

        $command = new TaskCommand(
            $descriptor,
            $this->createMock(ExpressionLanguage::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ContextRegistry::class),
            new Slugger(new AsciiSlugger()),
            new Filesystem(),
            new FileLinkFormatter(),
        );

        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete($input);

        $message = \sprintf('%s - Suggestions: %s',
            (new \ReflectionFunction($function))->getName(),
            implode(', ', $suggestions)
        );

        if ($exactExpectations) {
            $this->assertCount(\count($expectedItems), $suggestions, $message);
            $this->assertSame($expectedItems, $suggestions, $message);

            return;
        }

        $this->assertCount(\count($expectedItems), array_intersect($suggestions, $expectedItems), $message);
    }

    public static function providePathCompletionTests(): \Generator
    {
        $fs = new Filesystem();

        $tmpDir = sys_get_temp_dir() . '/castor-test';
        $fs->remove($tmpDir);
        $fs->mkdir($tmpDir . '/foo');
        $fs->mkdir($tmpDir . '/bar');
        $fs->touch($tmpDir . '/foo/baz.txt');

        yield [task_with_path_argument(...), [''], ['bin/', 'doc/', 'src/', 'tools/']];
        yield [task_with_path_argument(...), ['.'], ['bin/', 'doc/', 'src/', 'tools/']];
        yield [task_with_path_argument(...), ['./'], ['./bin/', './doc/', './src/', './tools/']];
        yield [task_with_path_argument(...), ['b'], ['bin/', 'doc/', 'src/', 'tools/']];
        yield [task_with_path_argument(...), ['bin'], ['bin/castor']];
        yield [task_with_path_argument(...), ['bin/'], ['bin/castor']];
        yield [task_with_path_argument(...), ['yolooooooooo'], []];
        yield [task_with_path_argument(...), ['yolooooooooo/'], []];
        yield [task_with_path_argument(...), [$tmpDir], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];
        yield [task_with_path_argument(...), [$tmpDir . '/'], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];
        yield [task_with_path_argument(...), [$tmpDir . '/.'], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];
        yield [task_with_path_argument(...), [$tmpDir . '/./'], [$tmpDir . '/./bar/', $tmpDir . '/./foo/'], true];
        yield [task_with_path_argument(...), [$tmpDir . '/f'], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];

        yield [task_with_path_option(...), ['--option'], ['bin/', 'doc/', 'src/', 'tools/']];
        yield [task_with_path_option(...), ['--option', '.'], ['bin/', 'doc/', 'src/', 'tools/']];
        yield [task_with_path_option(...), ['--option', './'], ['./bin/', './doc/', './src/', './tools/']];
        yield [task_with_path_option(...), ['--option', 'b'], ['bin/', 'doc/', 'src/', 'tools/']];
        yield [task_with_path_option(...), ['--option', 'bin'], ['bin/castor']];
        yield [task_with_path_option(...), ['--option', 'bin/'], ['bin/castor']];
        yield [task_with_path_option(...), ['--option', 'yolooooooooo'], []];
        yield [task_with_path_option(...), ['--option', 'yolooooooooo/'], []];
        yield [task_with_path_option(...), ['--option', $tmpDir], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];
        yield [task_with_path_option(...), ['--option', $tmpDir . '/'], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];
        yield [task_with_path_option(...), ['--option', $tmpDir . '/.'], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];
        yield [task_with_path_option(...), ['--option', $tmpDir . '/./'], [$tmpDir . '/./bar/', $tmpDir . '/./foo/'], true];
        yield [task_with_path_option(...), ['--option', $tmpDir . '/f'], [$tmpDir . '/bar/', $tmpDir . '/foo/'], true];

        yield [task_with_path_argument_directory(...), [''], ['config.yaml', 'routes.yaml', 'schema.xml', 'templates/'], true];
        yield [task_with_path_argument_directory(...), ['.'], ['config.yaml', 'routes.yaml', 'schema.xml', 'templates/'], true];

        $filterTmpDir = sys_get_temp_dir() . '/castor-filter-test';
        $fs->remove($filterTmpDir);
        $fs->mkdir($filterTmpDir);
        $fs->touch($filterTmpDir . '/app.php');
        $fs->touch($filterTmpDir . '/index.php');
        $fs->touch($filterTmpDir . '/readme.md');
        $fs->touch($filterTmpDir . '/index.html.twig');
        $fs->mkdir($filterTmpDir . '/subdir');

        yield [task_with_path_argument_filter(...), [$filterTmpDir . '/'], [$filterTmpDir . '/app.php', $filterTmpDir . '/index.php', $filterTmpDir . '/subdir/'], true];
        yield [task_with_path_argument_filters(...), [$filterTmpDir . '/'], [$filterTmpDir . '/index.html.twig', $filterTmpDir . '/readme.md', $filterTmpDir . '/subdir/'], true];

        yield [task_with_path_option_directory_filter(...), ['--option'], ['config.yaml', 'routes.yaml', 'templates/'], true];
        yield [task_with_path_option_directory_filter(...), ['--option', 'templates/'], [], true];
    }
}

function task_with_static_autocomplete(
    #[AsArgument(name: 'argument', autocomplete: ['a', 'b', 'c'])]
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
    ], static fn (string $value) => str_starts_with($value, $input->getCompletionValue()));
}

function task_with_path_argument(
    #[AsPathArgument(name: 'argument')]
    string $path,
): void {
}

function task_with_path_option(
    #[AsPathOption(name: 'option')]
    string $path,
): void {
}

function task_with_path_argument_directory(
    #[AsPathArgument(name: 'argument', directory: 'tests/fixtures/autocomplete')]
    string $path,
): void {
}

function task_with_path_argument_filter(
    #[AsPathArgument(name: 'argument', filter: '*.php')]
    string $path,
): void {
}

function task_with_path_argument_filters(
    #[AsPathArgument(name: 'argument', filter: ['*.twig', '*.md'])]
    string $path,
): void {
}

function task_with_path_option_directory_filter(
    #[AsPathOption(name: 'option', directory: 'tests/fixtures/autocomplete', filter: '*.yaml')]
    string $path,
): void {
}
