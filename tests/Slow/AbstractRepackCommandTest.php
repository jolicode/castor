<?php

namespace Castor\Tests\Slow;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractRepackCommandTest extends TaskTestCase
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

        // Only for the compile test
        $fs->dumpFile($castorAppDirPath . '/simple-logo-file.php', <<<'SIMPLELOGOFILE'
            <?php
            return 'My LOGO';
            SIMPLELOGOFILE
        );

        // Only for the compile test
        $fs->dumpFile($castorAppDirPath . '/closure-logo-file.php', <<<'CLOSURELOGOFILE'
            <?php
            return function (string $appName, string $appVersion) {
                return '/!\ This Special LOGO for ' . $appName . ' in version ' . $appVersion;
            };
            CLOSURELOGOFILE
        );

        return $castorAppDirPath;
    }
}
