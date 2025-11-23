<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ShebangTest extends TaskTestCase
{
    public static ?string $shebangTaskFile = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$shebangTaskFile = sys_get_temp_dir() . '/shebang-task-' . uniqid() . '.php';
    }

    public static function tearDownAfterClass(): void
    {
        $fs = new Filesystem();

        if (self::$shebangTaskFile && $fs->exists(self::$shebangTaskFile)) {
            $fs->remove(self::$shebangTaskFile);
        }

        parent::tearDownAfterClass();
    }

    // shebang-task
    public function test(): void
    {
        (new Filesystem())->copy(
            __DIR__ . '/../../examples/advanced/castor-file/shebang.php',
            self::$shebangTaskFile
        );

        $shebangTask = file_get_contents(self::$shebangTaskFile);
        if (false === $shebangTask) {
            throw new \RuntimeException('Failed to read shebang task file.');
        }

        // Modify the shebang line to use the currently tested castor binary
        $shebangTask = preg_replace(
            '/^#!.*castor --castor-file.*$/m',
            '#!' . self::$castorBin . ' --castor-file',
            $shebangTask
        );
        file_put_contents(self::$shebangTaskFile, $shebangTask);

        $process = $this->runTask(['shebang-task']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    protected function getCommand(array $args): array
    {
        return [self::$shebangTaskFile, '--no-ansi', ...$args];
    }
}
