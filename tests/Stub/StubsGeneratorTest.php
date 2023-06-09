<?php

namespace Castor\Tests\Stub;

use Castor\Stub\StubsGenerator;
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

        $generator = new StubsGenerator();
        $generator->generateStubs($file);

        $process = new Process([\PHP_BINARY, '-l', $file]);
        $process->run();

        $this->assertSame(0, $process->getExitCode());
    }
}
