<?php

namespace Castor\Tests\Runner;

use Castor\Console\Application;
use Castor\ContextRegistry;
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
