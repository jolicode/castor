<?php

namespace Castor\Tests\Slow;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RepackCommandTest extends TestCase
{
    public function test()
    {
        $finder = new ExecutableFinder();
        $box = $finder->find('box');

        if (null === $box) {
            $this->markTestSkipped('box is not installed.');
        }

        $castorAppDirPath = self::setupRepackedCastorApp('castor-test-repack');

        (new Process([
            'vendor/jolicode/castor/bin/castor',
            'repack',
            '--os', 'linux',
        ], cwd: $castorAppDirPath))->mustRun();

        $phar = $castorAppDirPath . '/my-app.linux.phar';
        $this->assertFileExists($phar);

        (new Process([$phar], cwd: $castorAppDirPath))->mustRun();

        $p = (new Process([$phar, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = (new Process([$phar, 'hello'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame('hello', $p->getOutput());
    }

    public static function setupRepackedCastorApp(string $castorAppDirName): string
    {
        $castorAppDirPath = sys_get_temp_dir() . '/' . $castorAppDirName;

        $fs = new Filesystem();
        $fs->remove($castorAppDirPath);
        $fs->mkdir($castorAppDirPath);
        $fs->dumpFile($castorAppDirPath . '/castor.php', <<<'PHP'
            <?php

            use Castor\Attribute\AsTask;

            #[AsTask()]
            function hello(): void
            {
                echo "hello";
            }
            PHP
        );

        $fs->dumpFile($castorAppDirPath . '/composer.json', json_encode([
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => __DIR__ . '/../..',
                ],
            ],
            'require' => [
                'jolicode/castor' => '*@dev',
            ],
        ]));

        (new Process(['composer', 'install'],
            cwd: $castorAppDirPath,
            env: ['COMPOSER_MIRROR_PATH_REPOS' => '1'],
        ))->mustRun();

        return $castorAppDirPath;
    }
}
