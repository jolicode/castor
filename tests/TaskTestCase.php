<?php

namespace Castor\Tests;

use Castor\Tests\Helper\OutputCleaner;
use Castor\Tests\Helper\WebServerHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

abstract class TaskTestCase extends TestCase
{
    public static string $castorBin;
    public static string $castorCacheDir;
    public static bool $binary = false;

    public static function setUpBeforeClass(): void
    {
        WebServerHelper::start();

        self::$castorBin = $_SERVER['CASTOR_BIN'] ?? __DIR__ . '/../bin/castor';
        self::$castorCacheDir = $_SERVER['CASTOR_CACHE_DIR'] ?? '/tmp/castor-tests/cache';
        if (!self::$castorCacheDir) {
            throw new \RuntimeException('CASTOR_CACHE_DIR is not set or empty.');
        }
        self::$binary = 'application/x-executable' === mime_content_type(self::$castorBin);
    }

    public function runTask(array $args, ?string $cwd = null, bool $needRemote = false, bool $needResetVendor = false, bool $needResetCache = true, ?string $input = null): Process
    {
        $coverage = $this->getTestResultObject()?->getCodeCoverage();

        $extraEnv = [
            'ENDPOINT' => $_SERVER['ENDPOINT'],
            'CASTOR_CACHE_DIR' => self::$castorCacheDir,
            'CASTOR_TEST' => 'true',
        ];

        if (!$needRemote) {
            $extraEnv['CASTOR_NO_REMOTE'] = 1;
        }

        if ($coverage) {
            self::$castorBin = __DIR__ . '/bin/castor';
            $testName = debug_backtrace()[1]['class'] . '::' . debug_backtrace()[1]['function'];
            $outputFilename = stream_get_meta_data(tmpfile())['uri'];
            $extraEnv['CC_OUTPUT_FILENAME'] = $outputFilename;
            $extraEnv['CC_TEST_NAME'] = $testName;
        }

        $workingDirectory = $cwd ? str_replace('{{ base }}', __DIR__ . '/..', $cwd) : __DIR__ . '/..';

        $fs = new Filesystem();
        if ($needResetCache) {
            $fs->remove(self::$castorCacheDir);
        }
        if ($needResetVendor) {
            $fs->remove($workingDirectory . '/.castor/vendor');
        }

        $inputStream = $input ? new InputStream() : null;

        $process = new Process(
            [self::$castorBin, '--no-ansi', ...$args],
            cwd: $workingDirectory,
            env: [
                'COLUMNS' => 1000,
                ...$extraEnv,
            ],
            input: $inputStream,
        );

        if ($inputStream) {
            $process->start();
            usleep(500_000);
            $inputStream->write($input);
            $inputStream->close();
            $process->wait();
        } else {
            $process->run();
        }

        if ($coverage) {
            $coverage->merge(require $outputFilename);
        }

        return $process;
    }

    public static function assertStringEqualsFile(string $expectedFile, string $actualString, string $message = ''): void
    {
        $actualString = OutputCleaner::cleanOutput($actualString);
        parent::assertStringEqualsFile($expectedFile, $actualString, $message);
    }
}
