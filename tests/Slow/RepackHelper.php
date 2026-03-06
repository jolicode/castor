<?php

namespace Castor\Tests\Slow;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class RepackHelper
{
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
                run(['ls', 'my-app.linux-amd64.phar']);
            }

            #[AsTask()]
            function timezone(): void
            {
                io()->write(date_default_timezone_get());
            }
            PHP
        );

        // Only for the compile test
        $fs->dumpFile($castorAppDirPath . '/php.ini', <<<'INI'
            date.timezone=Arctic/Longyearbyen
            INI
        );

        return $castorAppDirPath;
    }

    public static function repackApp(string $castorBin, string $castorAppDirPath, array $repackArgs = []): string
    {
        $process = new Process(
            [
                $castorBin,
                'repack',
                '--os', 'linux',
                ...$repackArgs,
            ],
            cwd: $castorAppDirPath
        );
        if ($_SERVER['GITHUB_TOKEN'] ?? false) {
            $process->setEnv(['GITHUB_TOKEN' => $_SERVER['GITHUB_TOKEN']]);
        }
        $process->mustRun();

        return $castorAppDirPath . '/my-app.linux-amd64.phar';
    }
}
