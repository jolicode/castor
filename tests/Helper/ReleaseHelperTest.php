<?php

namespace Castor\Tests\Helper;

use Castor\Helper\ReleaseHelper;
use PHPUnit\Framework\TestCase;

class ReleaseHelperTest extends TestCase
{
    private const string CHECKSUMS = <<<'TXT'
        1111111111111111111111111111111111111111111111111111111111111111  castor.linux-amd64
        2222222222222222222222222222222222222222222222222222222222222222  castor.linux-amd64.phar
        3333333333333333333333333333333333333333333333333333333333333333 *castor.darwin-arm64
        TXT;

    public function testTheChecksumOfAnAssetIsFound(): void
    {
        $helper = $this->createHelper();

        $this->assertSame(str_repeat('1', 64), $helper->getExpectedChecksum(self::CHECKSUMS, 'castor.linux-amd64'));
        $this->assertSame(str_repeat('2', 64), $helper->getExpectedChecksum(self::CHECKSUMS, 'castor.linux-amd64.phar'));
        $this->assertSame(str_repeat('3', 64), $helper->getExpectedChecksum(self::CHECKSUMS, 'castor.darwin-arm64'));
    }

    public function testAnUnlistedAssetIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no entry for "castor.windows-amd64.phar"');

        $this->createHelper()->getExpectedChecksum(self::CHECKSUMS, 'castor.windows-amd64.phar');
    }

    public function testTheChecksumsAssetIsFoundByName(): void
    {
        $helper = $this->createHelper();
        $release = ['assets' => [
            ['name' => 'castor.linux-amd64', 'url' => 'https://example.com/binary'],
            ['name' => 'SHA256SUMS', 'url' => 'https://example.com/checksums'],
        ]];

        $this->assertSame('https://example.com/checksums', $helper->getChecksumsAsset($release)['url'] ?? null);
        $this->assertNull($helper->getChecksumsAsset(['assets' => [['name' => 'castor.linux-amd64']]]));
    }

    private function createHelper(): ReleaseHelper
    {
        // The tested methods do not use the constructor dependencies
        return new \ReflectionClass(ReleaseHelper::class)->newInstanceWithoutConstructor();
    }
}
