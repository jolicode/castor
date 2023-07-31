<?php

namespace Castor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class RepackCommandTest extends TestCase
{
    public function test()
    {
        $tmp = sys_get_temp_dir() . '/castor-test-repack';

        $fs = new Filesystem();
        $fs->remove($tmp);
        $fs->mkdir($tmp);
        $fs->dumpFile($tmp . '/castor.php', <<<'PHP'
            <?php

            use Castor\Attribute\AsTask;

            #[AsTask()]
            function hello(): void
            {
                echo "hello";
            }
            PHP
        );

        $fs->dumpFile($tmp . '/composer.json', json_encode([
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => __DIR__ . '/..',
                ],
            ],
            'require' => [
                'jolicode/castor' => '*@dev',
                // UPGRADE: Remove this dependency when castor require symfony/console 6.4@stable
                'symfony/console' => '*@dev',
            ],
        ]));

        (new Process(['composer', 'install'],
            cwd: $tmp,
            env: ['COMPOSER_MIRROR_PATH_REPOS' => '1'],
        ))->mustRun();

        (new Process([
            'vendor/jolicode/castor/bin/castor',
            'repack',
            '--os', 'linux',
        ], cwd: $tmp))->mustRun();

        $phar = $tmp . '/my-app.linux.phar';
        $this->assertFileExists($phar);

        (new Process([$phar], cwd: $tmp))->mustRun();

        $p = (new Process([$phar, 'hello'], cwd: $tmp))->mustRun();
        $this->assertSame('hello', $p->getOutput());

        // Twice, because we want to be sure the phar is not corrupted after a
        // run
        $p = (new Process([$phar, 'hello'], cwd: $tmp))->mustRun();
        $this->assertSame('hello', $p->getOutput());
    }
}
