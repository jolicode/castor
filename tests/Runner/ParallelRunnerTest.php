<?php

namespace Castor\Tests\Runner;

use Castor\Console\Application;
use Castor\ContextRegistry;
use Castor\Exception\ParallelException;
use Castor\Runner\Parallel\JobRegistry;
use Castor\Runner\ParallelRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ParallelRunnerTest extends TestCase
{
    private BufferedOutput $errorOutput;
    private JobRegistry $jobRegistry;
    private BufferedOutput $output;
    private ParallelRunner $runner;

    protected function setUp(): void
    {
        $this->errorOutput = new BufferedOutput();
        $this->output = new class($this->errorOutput) extends BufferedOutput implements ConsoleOutputInterface {
            public function __construct(
                private OutputInterface $errorOutput,
            ) {
                parent::__construct();
            }

            public function getErrorOutput(): OutputInterface
            {
                return $this->errorOutput;
            }

            public function setErrorOutput(OutputInterface $error): void
            {
                $this->errorOutput = $error;
            }

            public function section(): ConsoleSectionOutput
            {
                throw new \LogicException('parallel() does not use sections.');
            }
        };
        $this->jobRegistry = new JobRegistry(new ArrayInput([]));

        $app = $this->createStub(Application::class);
        $app->method('renderThrowable')->willReturnCallback(static function (\Throwable $e, OutputInterface $output): void {
            $output->writeln("Error: {$e->getMessage()}", OutputInterface::VERBOSITY_QUIET);
        });

        $this->runner = new ParallelRunner($app, $this->output, $this->createStub(ContextRegistry::class), $this->jobRegistry);
    }

    public function testTheResultsOfNamedFunctionsAreKeyedByTheirName(): void
    {
        $results = $this->runner->parallel(lint: static fn () => 1, test: static fn () => 2);

        $this->assertSame(['lint' => 1, 'test' => 2], $results);
    }

    public function testTheResultsOfUnnamedFunctionsAreAList(): void
    {
        $results = $this->runner->parallel(static fn () => 1, static fn () => 2);

        $this->assertSame([1, 2], $results);
    }

    public function testMixingNamedAndUnnamedFunctionsIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The functions given to parallel() must be either all named or all unnamed.');

        $this->runner->parallel(...[static fn () => 1, 'test' => static fn () => 2]);
    }

    public function testTheResultsOfMapAreKeyedLikeTheItems(): void
    {
        $double = static fn (int $item) => $item * 2;

        $this->assertSame([2, 4], $this->runner->map([1, 2], $double));
        $this->assertSame([3 => 2, 7 => 4], $this->runner->map([3 => 1, 7 => 2], $double));
        $this->assertSame('', $this->output->fetch(), 'Items keyed by an integer are not jobs.');

        $this->assertSame(['web1' => 2, 'web2' => 4], $this->runner->map(['web1' => 1, 'web2' => 2], $double));
        $this->assertStringContainsString('[web1] ✔ Done in', $this->output->fetch());
    }

    public function testMixingItemsKeyedByANameAndByAnIntegerIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The items given to parallel_map() must be either all keyed by a name or all keyed by an integer.');

        $this->runner->map([1, 'web1' => 2], static fn (int $item) => $item);
    }

    public function testMapLimitsHowManyFunctionsRunAtTheSameTime(): void
    {
        $running = 0;
        $maxRunning = 0;
        $started = [];

        $results = $this->runner->map([1, 2, 3, 4, 5], static function (int $item) use (&$running, &$maxRunning, &$started): int {
            $started[] = $item;
            $maxRunning = max($maxRunning, ++$running);
            \Fiber::suspend();
            --$running;

            return $item * 2;
        }, 2);

        $this->assertSame(2, $maxRunning);
        $this->assertSame([1, 2, 3, 4, 5], $started);
        $this->assertSame([2, 4, 6, 8, 10], $results);
    }

    public function testAConcurrencyBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The concurrency given to parallel_map() must be at least 1.');

        $this->runner->map([1], static fn (int $item) => $item, 0);
    }

    public function testAFailureGivesTheResultsOfTheOtherFunctions(): void
    {
        try {
            $this->runner->map(
                ['web1' => 1, 'web2' => 2, 'web3' => 3],
                static fn (int $item) => 2 === $item ? throw new \RuntimeException('boom') : $item * 2,
            );
            $this->fail('The failure of a job is not reported.');
        } catch (ParallelException $e) {
            $this->assertSame(['web1' => 2, 'web3' => 6], $e->results);
            $this->assertSame(['web2'], array_keys($e->exceptions));
            $this->assertSame('boom', $e->exceptions['web2']->getMessage());
        }
    }

    public function testEachLineIsPrefixedWithTheNameOfItsJob(): void
    {
        $this->runner->parallel(
            lint: fn () => $this->writeInTwoSteps('lint'),
            test: fn () => $this->writeInTwoSteps('test'),
        );

        $this->assertSame(<<<'TXT'
            [lint] lint 1
            [test] test 1
            [lint] lint 2
            [lint] ✔ Done in X.Xs
            [test] test 2
            [test] ✔ Done in X.Xs

            TXT, $this->fetch($this->output));
        $this->assertSame('', $this->errorOutput->fetch());
    }

    /**
     * Each change of the status would flood the lines of the job.
     */
    public function testTheLastStatusOfAJobIsPrintedOnceItIsFinished(): void
    {
        $this->runner->parallel(
            lint: function (): void {
                $this->jobRegistry->getCurrent()?->setStatus('linting');
                \Fiber::suspend();
                $this->jobRegistry->getCurrent()?->setStatus('3 files <linted>');
            },
        );

        $this->assertSame("[lint] ✔ Done in X.Xs: 3 files <linted>\n", $this->fetch($this->output));
    }

    public function testAJobMarkedAsFailedOrSkippedStillReturnsItsResult(): void
    {
        $results = $this->runner->parallel(
            lint: function (): string {
                $this->jobRegistry->getCurrent()?->setStatus('unreachable');
                $this->jobRegistry->getCurrent()?->markAsFailed();

                return 'lint';
            },
            test: function (): string {
                $this->jobRegistry->getCurrent()?->markAsSkipped();

                return 'test';
            },
        );

        $this->assertSame(['lint' => 'lint', 'test' => 'test'], $results);
        $this->assertSame(<<<'TXT'
            [lint] ✘ Failed after X.Xs: unreachable
            [test] - Skipped after X.Xs

            [lint] ✘ Failed after X.Xs: unreachable
            [test] - Skipped after X.Xs

            TXT, $this->fetch($this->output));
        $this->assertSame('', $this->errorOutput->fetch());
    }

    public function testTheErrorOutputOfTheJobsGoesToTheErrorOutput(): void
    {
        $this->runner->parallel(
            lint: fn () => $this->jobRegistry->getCurrent()?->errorOutput->writeln('warning'),
        );

        $this->assertSame("[lint] warning\n", $this->errorOutput->fetch());
    }

    public function testAFailureIsPrintedInItsJobAndTheJobsAreSummarized(): void
    {
        try {
            $this->runner->parallel(
                lint: fn () => $this->writeInTwoSteps('lint'),
                test: static fn () => throw new \RuntimeException('boom'),
            );
            $this->fail('The failure of a job is not reported.');
        } catch (\RuntimeException $e) {
            $this->assertSame('One or more exceptions were thrown in parallel.', $e->getMessage());
        }

        $this->assertSame(<<<'TXT'
            [lint] lint 1
            [test] ✘ Failed after X.Xs
            [lint] lint 2
            [lint] ✔ Done in X.Xs

            [lint] ✔ Done in X.Xs
            [test] ✘ Failed after X.Xs

            TXT, $this->fetch($this->output));
        $this->assertSame("[test] Error: boom\n", $this->errorOutput->fetch());
    }

    public function testTheErrorOfAJobIsPrintedInQuietMode(): void
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $this->errorOutput->setVerbosity(OutputInterface::VERBOSITY_QUIET);

        try {
            $this->runner->parallel(
                test: static fn () => throw new \RuntimeException('boom'),
            );
            $this->fail('The failure of a job is not reported.');
        } catch (\RuntimeException $e) {
            $this->assertSame('One or more exceptions were thrown in parallel.', $e->getMessage());
        }

        $this->assertSame('', $this->output->fetch());
        $this->assertSame("[test] Error: boom\n", $this->errorOutput->fetch());
    }

    public function testWhatAJobEchoesIsPrefixed(): void
    {
        $this->runner->parallel(
            lint: static function (): void {
                echo "echoed\n";
            },
        );

        $this->assertStringStartsWith("[lint] echoed\n", $this->output->fetch());
    }

    public function testTheLastLineOfAFailedJobComesBeforeItsError(): void
    {
        try {
            $this->runner->parallel(
                test: function (): void {
                    $this->jobRegistry->getCurrent()?->errorOutput->write('no new line');

                    throw new \RuntimeException('boom');
                },
            );
            $this->fail('The failure of a job is not reported.');
        } catch (\RuntimeException $e) {
            $this->assertSame('One or more exceptions were thrown in parallel.', $e->getMessage());
        }

        $this->assertSame("[test] no new line\n[test] Error: boom\n", $this->errorOutput->fetch());
    }

    public function testNamedFunctionsNestedInAJobArePrefixedInIt(): void
    {
        $this->runner->parallel(
            build: fn () => $this->runner->parallel(
                assets: fn () => $this->jobRegistry->getCurrent()?->output->writeln('compiled'),
            ),
        );

        $this->assertStringStartsWith("[build] [assets] compiled\n", $this->fetch($this->output));
    }

    public function testTheErrorOfUnnamedFunctionsNestedInAJobStaysInIt(): void
    {
        try {
            $this->runner->parallel(
                build: fn () => $this->runner->parallel(
                    static fn () => throw new \RuntimeException('inner'),
                ),
            );
            $this->fail('The failure of a job is not reported.');
        } catch (\RuntimeException $e) {
            $this->assertSame('One or more exceptions were thrown in parallel.', $e->getMessage());
        }

        $this->assertStringStartsWith("[build] Error: inner\n", $this->errorOutput->fetch());
    }

    private function writeInTwoSteps(string $name): void
    {
        $this->jobRegistry->getCurrent()?->output->writeln("{$name} 1");
        \Fiber::suspend();
        $this->jobRegistry->getCurrent()?->output->writeln("{$name} 2");
    }

    private function fetch(BufferedOutput $output): string
    {
        return (string) preg_replace('{\d+\.\ds}', 'X.Xs', $output->fetch());
    }
}
