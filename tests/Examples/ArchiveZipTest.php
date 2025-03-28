<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @test test
 */
class ArchiveZipTest extends TaskTestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../../examples/fixtures/archive';
    private const SAMPLE_FILE_PATH = self::FIXTURES_DIR . '/sample.txt';
    private const SAMPLE_FILE_ZIP_PATH = self::FIXTURES_DIR . '/sample.zip';
    private const SAMPLE_DIR_PATH = self::FIXTURES_DIR . '/sample_dir';
    private const SAMPLE_DIR_ZIP_PATH = self::FIXTURES_DIR . '/sample_dir.zip';

    private Filesystem $filesystem;
    private string $sampleFileContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();

        // Use longer content to ensure zip binary actually applies compression.
        // With very short content, zip may determine compression is unnecessary and leave the file uncompressed.
        $this->sampleFileContent = str_repeat('This is a sample text file for testing zip functionality.', 20);

        if (!$this->filesystem->exists(self::FIXTURES_DIR)) {
            $this->filesystem->mkdir(self::FIXTURES_DIR);
        }

        $this->filesystem->dumpFile(self::SAMPLE_FILE_PATH, $this->sampleFileContent);

        if (!$this->filesystem->exists(self::SAMPLE_DIR_PATH)) {
            $this->filesystem->mkdir(self::SAMPLE_DIR_PATH);
        }

        $this->filesystem->dumpFile(self::SAMPLE_DIR_PATH . '/sample.txt', $this->sampleFileContent);

        // Create a subdirectory with another text file to test recursivity
        if (!$this->filesystem->exists(self::SAMPLE_DIR_PATH . '/subdir')) {
            $this->filesystem->mkdir(self::SAMPLE_DIR_PATH . '/subdir');
        }

        $this->filesystem->dumpFile(self::SAMPLE_DIR_PATH . '/subdir/nested.txt', $this->sampleFileContent);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove([
            self::SAMPLE_FILE_PATH,
            self::SAMPLE_FILE_ZIP_PATH,
            self::SAMPLE_DIR_PATH,
            self::SAMPLE_DIR_ZIP_PATH,
        ]);

        parent::tearDown();
    }

    /**
     * @dataProvider archiveTaskProvider
     */
    public function testArchiveTask(string $taskName): void
    {
        $process = $this->runTask([$taskName]);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertFileExists(self::SAMPLE_FILE_ZIP_PATH);
        $this->assertFileExists(self::SAMPLE_DIR_ZIP_PATH);

        $this->assertZipIsPasswordProtected(self::SAMPLE_FILE_ZIP_PATH, 'sample.txt');
        $this->assertZipCompressionMethod(self::SAMPLE_FILE_ZIP_PATH);

        $this->assertZipIsPasswordProtected(self::SAMPLE_DIR_ZIP_PATH, 'sample_dir/sample.txt');
        $this->assertZipIsPasswordProtected(self::SAMPLE_DIR_ZIP_PATH, 'sample_dir/subdir/nested.txt');

        $this->assertZipCompressionMethod(self::SAMPLE_DIR_ZIP_PATH);
    }

    public function archiveTaskProvider(): \Generator
    {
        yield 'archive:zip' => ['archive:zip'];
        yield 'archive:zip-binary' => ['archive:zip-binary'];
        yield 'archive:zip-php' => ['archive:zip-php'];
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
        $this->assertSame($this->sampleFileContent, $extracted);

        $zip->close();
    }

    private function assertZipCompressionMethod(string $zipPath): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive class not available');
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $zip->setPassword('secret');
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            ['comp_method' => $compressionMethod, 'name' => $filePath] = $zip->statIndex($i);

            if (str_ends_with($filePath, '/')) {
                $this->assertEquals(\ZipArchive::CM_STORE, $compressionMethod, \sprintf('Compression method is "%d" for directory "%s", should be "%d"', $compressionMethod, $filePath, \ZipArchive::CM_STORE));

                continue;
            }

            $this->assertEquals(\ZipArchive::CM_BZIP2, $compressionMethod, \sprintf('Compression method is "%d" for file "%s", should be "%d"', $compressionMethod, $filePath, \ZipArchive::CM_BZIP2));
        }

        $zip->close();
    }
}
