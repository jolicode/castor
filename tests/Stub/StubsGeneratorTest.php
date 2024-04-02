<?php

namespace Castor\Tests\Stub;

use Castor\Stub\StubsGenerator;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class StubsGeneratorTest extends TestCase
{
    public function test()
    {
        $file = sys_get_temp_dir() . '/castor.stub.php';

        $fs = new Filesystem();
        $fs->remove($file);

        $generator = new StubsGenerator(sys_get_temp_dir(), new Logger('name'));
        (fn ($file) => $generator->generateStubs($file))->bindTo($generator, StubsGenerator::class)($file);

        $process = new Process([\PHP_BINARY, '-l', $file]);
        $process->run();

        $this->assertSame(0, $process->getExitCode());
    }
}
