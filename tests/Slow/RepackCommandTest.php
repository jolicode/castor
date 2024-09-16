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
            './bin/castor',
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

        // Test remote
        $p = (new Process([$phar, 'pyrech:hello-example'], cwd: $castorAppDirPath))->mustRun();
        $this->assertSame("\nHello from example!\n===================\n\n", $p->getOutput());

        // Ensure the Root is well set
        $p = (new Process([$phar, 'ls'], cwd: $castorAppDirPath))->mustRun();
        $this->assertEquals('my-app.linux.phar', trim($p->getOutput()));
    }

    public static function setupRepackedCastorApp(string $castorAppDirName): string
    {
        $castorAppDirPath = sys_get_temp_dir() . '/' . $castorAppDirName;

        $fs = new Filesystem();
        $fs->remove($castorAppDirPath);
        $fs->mkdir($castorAppDirPath);
        $fs->dumpFile($castorAppDirPath . '/castor.composer.json', <<<'JSON'
            {
                "config": {
                    "sort-packages": true
                },
                "require": {
                    "pyrech/castor-example": "^1.0"
                }
            }
            JSON
        );

        $fs->dumpFile($castorAppDirPath . '/castor.php', <<<'PHP'
            <?php

            use Castor\Attribute\AsTask;

            use function Castor\import;
            use function Castor\io;
            use function Castor\run;

            import('composer://pyrech/castor-example');

            #[AsTask()]
            function hello(): void
            {
                echo "hello";
            }

            #[AsTask()]
            function ls(): void
            {
                run(['ls', 'my-app.linux.phar']);
            }

            #[AsTask()]
            function timezone(): void
            {
                io()->write(date_default_timezone_get());
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

        // Only for the compile test
        $fs->dumpFile($castorAppDirPath . '/php.ini', <<<'INI'
            date.timezone=Arctic/Longyearbyen
            INI
        );

        (new Process(['composer', 'install'],
            cwd: $castorAppDirPath,
            env: ['COMPOSER_MIRROR_PATH_REPOS' => '1'],
        ))->mustRun();

        return $castorAppDirPath;
    }
}
