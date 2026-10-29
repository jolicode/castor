<?php

namespace Castor\Tests\Import\Remote;

use Castor\Import\Remote\InstalledPackages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class InstalledPackagesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        // The files PHP loads have a real path, like "/private/tmp" for "/tmp" on macOS
        $this->directory = realpath(sys_get_temp_dir()) . '/castor-tests/installed-packages-' . uniqid();

        $fs = new Filesystem();
        $fs->mkdir([
            $this->directory . '/project/.castor/vendor/acme/package/nested',
            $this->directory . '/project/packages/local',
            $this->directory . '/elsewhere/linked',
        ]);
        // Like Composer does for a path repository
        $fs->symlink($this->directory . '/elsewhere/linked', $this->directory . '/project/.castor/vendor/acme/linked');
        $fs->dumpFile($this->directory . '/project/.castor/vendor/composer/installed.php', <<<'PHP'
            <?php return array(
                'root' => array(
                    'name' => 'acme/root',
                    'install_path' => __DIR__ . '/../../../',
                ),
                'versions' => array(
                    'acme/root' => array(
                        'install_path' => __DIR__ . '/../../../',
                    ),
                    'acme/linked' => array(
                        'install_path' => __DIR__ . '/../acme/linked',
                    ),
                    'acme/local' => array(
                        'install_path' => __DIR__ . '/../../../packages/local',
                    ),
                    'acme/meta' => array(
                        'install_path' => null,
                    ),
                    'acme/nested' => array(
                        'install_path' => __DIR__ . '/../acme/package/nested',
                    ),
                    'acme/package' => array(
                        'install_path' => __DIR__ . '/../acme/package',
                    ),
                    'acme/virtual' => array(
                        'provided' => array('1.0'),
                    ),
                ),
            );
            PHP);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAFileBelongsToThePackageHoldingIt(): void
    {
        $packages = $this->createInstalledPackages();
        $project = $this->directory . '/project';

        $this->assertSame('acme/package', $packages->findPackage($project . '/.castor/vendor/acme/package/src/functions.php', $project));
        // The deepest one
        $this->assertSame('acme/nested', $packages->findPackage($project . '/.castor/vendor/acme/package/nested/functions.php', $project));
        // From a path repository, which the files are loaded from
        $this->assertSame('acme/linked', $packages->findPackage($this->directory . '/elsewhere/linked/functions.php', $project));
        $this->assertSame('acme/local', $packages->findPackage($project . '/packages/local/functions.php', $project));
    }

    public function testAFileOfTheRootPackageBelongsToTheMount(): void
    {
        $packages = $this->createInstalledPackages();
        $project = $this->directory . '/project';

        $this->assertNull($packages->findPackage($project . '/src/functions.php', $project));
        $this->assertNull($packages->findPackage($this->directory . '/elsewhere/functions.php', $project));
    }

    public function testAFileOfAPackageHoldingTheMountBelongsToTheMount(): void
    {
        $packages = $this->createInstalledPackages();

        // The package required from its own repository
        $local = $this->directory . '/project/packages/local';
        $this->assertNull($packages->findPackage($local . '/functions.php', $local));

        // An example inside the package, requiring it
        $example = $local . '/example';
        $this->assertNull($packages->findPackage($example . '/src/functions.php', $example));
        $this->assertSame('acme/local', $packages->findPackage($local . '/src/functions.php', $example));
    }

    public function testTheVendorDirectoriesAreKeptInOrder(): void
    {
        $packages = $this->createInstalledPackages();
        $packages->addVendorDirectory($this->directory . '/mounted/.castor/vendor');

        $this->assertSame([$this->directory . '/project/.castor/vendor', $this->directory . '/mounted/.castor/vendor'], $packages->getVendorDirectories());
    }

    private function createInstalledPackages(): InstalledPackages
    {
        $packages = new InstalledPackages();
        $packages->addVendorDirectory($this->directory . '/project/.castor/vendor');

        return $packages;
    }
}
