<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArchiveZipTest extends TaskTestCase
{
    private const EXAMPLE_DIR = __DIR__ . '/../../examples/basic/archive';

    protected function setUp(): void
    {
        $this->clean();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->clean();

        parent::tearDown();
    }

    /**
     * @dataProvider archiveTaskProvider
     */
    public function testArchiveTask(string $taskName, string $filePath, string $expectedFileZipPath, string $expectedDirZipPath): void
    {
        $process = $this->runTask([$taskName]);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $expectedFileZipPath = self::EXAMPLE_DIR . '/' . $expectedFileZipPath;
        $this->assertFileExists($expectedFileZipPath);
        $this->assertZipIsPasswordProtected($expectedFileZipPath, $filePath);

        $expectedDirZipPath = self::EXAMPLE_DIR . '/' . $expectedDirZipPath;
        $this->assertFileExists($expectedDirZipPath);
        $this->assertZipIsPasswordProtected($expectedDirZipPath, 'zip.php');
        $this->assertZipIsPasswordProtected($expectedDirZipPath, 'zip_php.php');
        $this->assertZipIsPasswordProtected($expectedDirZipPath, 'zip_binary.php');
        $this->assertZipIsPasswordProtected($expectedDirZipPath, 'foobar/lorem-ipsum.txt');
    }

    public function archiveTaskProvider(): \Generator
    {
        yield 'archive:zip' => [
            'archive:zip',
            'zip.php',
            'archive.zip',
            'archive_dir.zip',
        ];

        yield 'archive:zip-binary' => [
            'archive:zip-binary',
            'zip_binary.php',
            'archive_binary.zip',
            'archive_binary_dir.zip',
        ];

        yield 'archive:zip-php' => [
            'archive:zip-php',
            'zip_php.php',
            'archive_php.zip',
            'archive_php_dir.zip',
        ];
    }

    private function clean(): void
    {
        $files = (new Finder())
            ->in(self::EXAMPLE_DIR)
            ->name('*.zip')
        ;

        (new Filesystem())->remove($files);
    }

    private function assertZipIsPasswordProtected(string $zipPath, string $filePath): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive class not available');
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $extracted = $zip->getFromName($filePath);
        $this->assertFalse($extracted);

        $zip->setPassword('secret');

        $extracted = $zip->getFromName($filePath);

        $expectedContent = file_get_contents(self::EXAMPLE_DIR . '/' . $filePath);

        $this->assertSame($expectedContent, $extracted, $filePath . ' content does not match after extraction from password protected zip.');

        $zip->close();
    }
}
