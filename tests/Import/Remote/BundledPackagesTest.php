<?php

namespace Castor\Tests\Import\Remote;

use Castor\Console\Application;
use Castor\Import\Remote\BundledPackages;
use Composer\InstalledVersions;
use Composer\Package\CompletePackage;
use PHPUnit\Framework\TestCase;

class BundledPackagesTest extends TestCase
{
    private const string VENDOR_DIR = __DIR__ . '/fixtures/vendor';

    public function testThePackagesOfTheVendorAreListed(): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0');

        // Not the root package, nor a package whose files are left out
        $this->assertSame([
            'foo/shipped' => 'v1.2.3',
            'jolicode/castor' => 'v1.7.0',
        ], self::getVersions($packages));
        $this->assertSame(['foo/implementation' => '1.0|2.0 || 3.0.0'], new \ReflectionMethod($packages, 'getProvides')->invoke($packages));
    }

    public function testASnapshotStandsForTheReleaseItIsBasedOn(): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0-14-g4531440');

        $this->assertSame('v1.7.0', self::getVersions($packages)['jolicode/castor']);
    }

    public function testTheVendorOfCastorIsDetected(): void
    {
        $versions = self::getVersions(new BundledPackages());

        $this->assertSame(InstalledVersions::getPrettyVersion('symfony/console'), $versions['symfony/console']);
        $this->assertSame(InstalledVersions::getPrettyVersion('nikic/php-parser'), $versions['nikic/php-parser']);
        // The root package, listed at the running version
        $this->assertSame(Application::VERSION, $versions['jolicode/castor']);
    }

    public function testTheHashFollowsThePackages(): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0');

        $this->assertSame($packages->getHash(), new BundledPackages(self::VENDOR_DIR, 'v1.7.0')->getHash());
        $this->assertNotSame($packages->getHash(), new BundledPackages(self::VENDOR_DIR, 'v1.8.0')->getHash());
        $this->assertNotSame($packages->getHash(), new BundledPackages()->getHash());
    }

    public function testTheRepositoryHoldsAMetapackagePerShippedPackage(): void
    {
        $repository = new BundledPackages(self::VENDOR_DIR, 'v1.7.0-14-g4531440')->createRepository();

        $this->assertSame('the packages bundled with Castor v1.7.0-14-g4531440', $repository->getRepoName());

        $packages = [];
        foreach ($repository->getPackages() as $package) {
            $this->assertInstanceOf(CompletePackage::class, $package);
            $packages[$package->getName()] = $package;
        }

        $this->assertSame(['foo/shipped', 'jolicode/castor'], array_keys($packages));
        $this->assertSame('metapackage', $packages['foo/shipped']->getType());
        $this->assertSame('v1.2.3', $packages['foo/shipped']->getPrettyVersion());
        $this->assertSame([], $packages['foo/shipped']->getProvides());
        // The mark that tells the entries of the lock file apart
        $this->assertSame(['castor' => ['bundled' => true]], $packages['foo/shipped']->getExtra());
        $this->assertTrue(BundledPackages::isBundled(['name' => 'foo/shipped', 'extra' => $packages['foo/shipped']->getExtra()]));
        $this->assertFalse(BundledPackages::isBundled(['name' => 'foo/shipped']));
        $this->assertFalse(BundledPackages::isBundled(['name' => 'foo/shipped', 'extra' => ['castor' => ['bundled' => 'yes']]]));

        // Castor itself carries what the shipped packages provide and replace
        $this->assertSame('metapackage', $packages['jolicode/castor']->getType());
        $this->assertSame('v1.7.0', $packages['jolicode/castor']->getPrettyVersion());
        $this->assertSame(['foo/implementation'], array_keys($packages['jolicode/castor']->getProvides()));
        $this->assertSame('1.0|2.0 || 3.0.0', $packages['jolicode/castor']->getProvides()['foo/implementation']->getPrettyConstraint());

        // Only the shipped version exists
        $this->assertCount(1, $repository->findPackages('foo/shipped', '^1.2'));
        $this->assertCount(0, $repository->findPackages('foo/shipped', '^2.0'));
        $this->assertCount(0, $repository->findPackages('foo/left-out'));
    }

    /**
     * @param list<string>         $expected
     * @param array<string, mixed> $lock
     * @param array<string, mixed> $composerJson
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideLocks')]
    public function testTheEntriesOfTheLockThatDoNotMatchTheShippedPackagesAreFound(array $expected, array $lock, array $composerJson = []): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0');

        $this->assertSame($expected, $packages->findOutdatedInLock($lock, $composerJson));
    }

    /**
     * @return iterable<string, array{list<string>, array<string, mixed>, 2?: array<string, mixed>}>
     */
    public static function provideLocks(): iterable
    {
        $bundled = ['extra' => ['castor' => ['bundled' => true]]];

        yield 'an empty lock' => [[], []];
        yield 'a package Castor does not ship' => [[], ['packages' => [['name' => 'bar/bar', 'version' => '1.0.0', 'type' => 'library']]]];
        yield 'a real package Castor now ships' => [['foo/shipped'], ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.3', 'type' => 'library']]]];
        yield 'a real dev package Castor now ships' => [['foo/shipped'], ['packages-dev' => [['name' => 'foo/shipped', 'version' => 'v1.2.3', 'type' => 'library']]]];
        yield 'a metapackage of the shipped version' => [[], ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.3', 'type' => 'metapackage'] + $bundled]]];
        yield 'a metapackage of a package Castor no longer ships' => [['foo/gone'], ['packages' => [['name' => 'foo/gone', 'version' => '2.0.0', 'type' => 'metapackage'] + $bundled]]];
        yield 'a metapackage of another version, without constraint' => [[], ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled]]];
        yield 'a metapackage of another version, satisfying castor.composer.json' => [
            [],
            ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled]],
            ['require' => ['foo/shipped' => '^1.2']],
        ];
        yield 'a metapackage of another version, not satisfying castor.composer.json' => [
            ['foo/shipped'],
            ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled]],
            ['require' => ['foo/shipped' => '1.2.0']],
        ];
        yield 'a metapackage of another version, not satisfying the dev requirements of castor.composer.json' => [
            ['foo/shipped'],
            ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled]],
            ['require-dev' => ['foo/shipped' => '<1.2.3']],
        ];
        yield 'a metapackage of another version, satisfying a locked package' => [
            [],
            ['packages' => [
                ['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled,
                ['name' => 'bar/bar', 'version' => '1.0.0', 'type' => 'library', 'require' => ['foo/shipped' => '>=1.2 <2.0', 'php' => '>=8.4']],
            ]],
        ];
        yield 'a metapackage of another version, not satisfying a locked package' => [
            ['foo/shipped'],
            ['packages' => [
                ['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled,
                ['name' => 'bar/bar', 'version' => '1.0.0', 'type' => 'library', 'require' => ['foo/shipped' => '~1.2.0', 'foo/other' => '*']],
                ['name' => 'baz/baz', 'version' => '1.0.0', 'type' => 'library', 'require' => ['foo/shipped' => '1.2.0']],
            ]],
        ];
        yield 'a metapackage of another version, conflicting with a locked package' => [
            ['foo/shipped'],
            ['packages' => [
                ['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled,
                ['name' => 'bar/bar', 'version' => '1.0.0', 'type' => 'library', 'conflict' => ['foo/shipped' => '>=1.2.3']],
            ]],
        ];
        yield 'a metapackage of another version, only the dev requirements of a locked package disagree' => [
            [],
            ['packages' => [
                ['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled,
                ['name' => 'bar/bar', 'version' => '1.0.0', 'type' => 'library', 'require-dev' => ['foo/shipped' => '1.2.0']],
            ]],
        ];
        yield 'a metapackage of another version, with a constraint Composer would have to judge' => [
            ['foo/shipped'],
            ['packages' => [['name' => 'foo/shipped', 'version' => 'v1.2.0', 'type' => 'metapackage'] + $bundled]],
            ['require' => ['foo/shipped' => 'not a constraint']],
        ];
        yield 'a mix' => [
            ['foo/gone', 'foo/shipped'],
            ['packages' => [
                ['name' => 'bar/bar', 'version' => '1.0.0', 'type' => 'library'],
                ['name' => 'foo/gone', 'version' => '2.0.0', 'type' => 'metapackage'] + $bundled,
                ['name' => 'foo/shipped', 'version' => 'v1.2.3', 'type' => 'library'],
                ['name' => 'jolicode/castor', 'version' => 'v1.6.0', 'type' => 'metapackage'] + $bundled,
            ]],
            ['require' => ['jolicode/castor' => '^1.6']],
        ];
    }

    public function testTheOptOutIsReadFromTheExtraKey(): void
    {
        $this->assertTrue(BundledPackages::isEnabled([]));
        $this->assertTrue(BundledPackages::isEnabled(['castor' => []]));
        $this->assertTrue(BundledPackages::isEnabled(['castor' => ['bundled-packages' => true]]));
        $this->assertFalse(BundledPackages::isEnabled(['castor' => ['bundled-packages' => false]]));
    }

    /**
     * @return array<string, string>
     */
    private static function getVersions(BundledPackages $packages): array
    {
        return new \ReflectionMethod($packages, 'getVersions')->invoke($packages);
    }
}
