<?php

namespace Castor\Tests\Import\Remote;

use Castor\Console\Application;
use Castor\Import\Remote\BundledPackages;
use Composer\InstalledVersions;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;

class BundledPackagesTest extends TestCase
{
    private const string VENDOR_DIR = __DIR__ . '/fixtures/vendor';

    public function testThePackagesOfTheVendorAreListed(): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0');

        // Not the root package, nor a package whose files are left out
        $this->assertSame([
            'castor/castor' => 'v1.7.0',
            'foo/replaced' => '*',
            'foo/shipped' => 'v1.2.3',
        ], $packages->getReplaces());
        $this->assertSame([
            'foo/implementation' => '1.0|2.0 || 3.0.0',
        ], $packages->getProvides());
    }

    public function testASnapshotStandsForTheReleaseItIsBasedOn(): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0-14-g4531440');

        $this->assertSame('v1.7.0', $packages->getReplaces()['castor/castor']);
    }

    public function testTheVendorOfCastorIsDetected(): void
    {
        $replaces = new BundledPackages()->getReplaces();

        $this->assertArrayNotHasKey('jolicode/castor', $replaces);
        $this->assertSame(InstalledVersions::getPrettyVersion('symfony/console'), $replaces['symfony/console']);
        $this->assertSame(InstalledVersions::getPrettyVersion('nikic/php-parser'), $replaces['nikic/php-parser']);
        $this->assertSame(Application::VERSION, $replaces['castor/castor']);
    }

    public function testTheHashFollowsThePackages(): void
    {
        $packages = new BundledPackages(self::VENDOR_DIR, 'v1.7.0');

        $this->assertSame($packages->getHash(), new BundledPackages(self::VENDOR_DIR, 'v1.7.0')->getHash());
        $this->assertNotSame($packages->getHash(), new BundledPackages(self::VENDOR_DIR, 'v1.8.0')->getHash());
        $this->assertNotSame($packages->getHash(), new BundledPackages()->getHash());
    }

    public function testThePackagesAreDeclaredOnTheRootPackage(): void
    {
        $parser = new VersionParser();
        $root = new RootPackage('acme/project', '1.0.0.0', '1.0.0');
        $root->setReplaces([
            'acme/own' => new Link('acme/project', 'acme/own', $parser->parseConstraints('1.0.0'), Link::TYPE_REPLACE, '1.0.0'),
            'castor/castor' => new Link('acme/project', 'castor/castor', $parser->parseConstraints('v0.15.0'), Link::TYPE_REPLACE, 'v0.15.0'),
        ]);

        new BundledPackages(self::VENDOR_DIR, 'v1.7.0')->applyTo($root);

        $replaces = $root->getReplaces();
        $this->assertSame(['acme/own', 'castor/castor', 'foo/replaced', 'foo/shipped'], array_keys($replaces));
        $this->assertSame('1.0.0', $replaces['acme/own']->getPrettyConstraint());
        // Castor wins over the file
        $this->assertSame('v1.7.0', $replaces['castor/castor']->getPrettyConstraint());
        $this->assertSame('acme/project', $replaces['foo/shipped']->getSource());
        $this->assertTrue($replaces['foo/shipped']->getConstraint()->matches($parser->parseConstraints('^1.2')));
        $this->assertFalse($replaces['foo/shipped']->getConstraint()->matches($parser->parseConstraints('^2.0')));

        $provides = $root->getProvides();
        $this->assertSame(['foo/implementation'], array_keys($provides));
        $this->assertSame(Link::TYPE_PROVIDE, $provides['foo/implementation']->getDescription());
        $this->assertTrue($provides['foo/implementation']->getConstraint()->matches($parser->parseConstraints('^3.0')));
    }

    public function testTheOptOutIsReadFromTheExtraKey(): void
    {
        $this->assertTrue(BundledPackages::isReplaceEnabled([]));
        $this->assertTrue(BundledPackages::isReplaceEnabled(['castor' => []]));
        $this->assertTrue(BundledPackages::isReplaceEnabled(['castor' => ['replace-bundled-packages' => true]]));
        $this->assertFalse(BundledPackages::isReplaceEnabled(['castor' => ['replace-bundled-packages' => false]]));
    }
}
