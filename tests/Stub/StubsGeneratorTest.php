<?php

namespace Castor\Tests\Stub;

use Castor\Stub\StubsGenerator;
use Monolog\Logger;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class StubsGeneratorTest extends TestCase
{
    public function testGenerateCastorStubs()
    {
        $file = sys_get_temp_dir() . '/castor.stub.php';

        $fs = new Filesystem();
        $fs->remove($file);

        $generator = new StubsGenerator(sys_get_temp_dir(), new Logger('name'));
        (fn ($file) => $generator->generateCastorStubs($file))->bindTo($generator, StubsGenerator::class)($file);

        $process = new Process([\PHP_BINARY, '-l', $file]);
        $process->run();

        $this->assertSame(0, $process->getExitCode());
    }

    /**
     * @dataProvider provideFixtures
     */
    public function testGenerateFixturesStubs(string $directory)
    {
        $expected = $directory . '/expected.php';
        $expectedCode = trim(file_get_contents($expected));

        $finder = (new Finder())
            ->files()
            ->in($directory)
            ->name('input*.php')
            ->sortByName()
        ;

        $generator = new StubsGenerator(sys_get_temp_dir(), new Logger('name'));
        $stmts = (fn (array $files) => $generator->doGenerate($files))->bindTo($generator, StubsGenerator::class)(iterator_to_array($finder));

        $generatedCode = (new Standard())->prettyPrintFile($stmts);

        $this->assertSame($expectedCode, $generatedCode, "Fail to assert the generated code is identical for fixture \"{$directory}\"");
    }

    public function provideFixtures(): \Generator
    {
        $dirs = (new Finder())
            ->in(__DIR__ . '/fixtures')
            ->directories()
            ->sortByName()
        ;

        foreach ($dirs as $dir) {
            yield $dir->getBasename() => [$dir->getRealPath()];
        }
    }
}
