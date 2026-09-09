<?php

namespace Castor\Tests\Http;

use Castor\Http\HttpDownloader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpDownloaderTest extends TestCase
{
    #[DataProvider('provideFileNames')]
    public function testFileNameIsRestrictedToItsLastSegment(string $url, ?string $contentDisposition, string $expected): void
    {
        $this->assertSame($expected, $this->extractFileName($url, $contentDisposition));
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function provideFileNames(): iterable
    {
        yield 'from the URL' => ['https://example.com/dist/archive.tar.gz', null, 'archive.tar.gz'];
        yield 'from the URL, ignoring the query string' => ['https://example.com/dist/archive.tar.gz?token=abc', null, 'archive.tar.gz'];
        yield 'from a directory URL' => ['https://example.com/dist/', null, 'dist'];
        yield 'from the header' => ['https://example.com/download?id=42', 'attachment; filename="report.pdf"', 'report.pdf'];
        yield 'from the header, with a relative path' => ['https://example.com/download', 'attachment; filename="../../.bashrc"', '.bashrc'];
        yield 'from the header, with an absolute path' => ['https://example.com/download', 'attachment; filename="/etc/passwd"', 'passwd'];
        yield 'from the header, with a Windows path' => ['https://example.com/download', 'attachment; filename="..\..\evil.txt"', 'evil.txt'];
        yield 'from the header, with control characters' => ['https://example.com/download', "attachment; filename=\"evil\r\n.txt\"", 'evil.txt'];
    }

    #[DataProvider('provideInvalidFileNames')]
    public function testInvalidFileNamesAreRejected(string $url, ?string $contentDisposition): void
    {
        $this->expectException(\RuntimeException::class);

        $this->extractFileName($url, $contentDisposition);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function provideInvalidFileNames(): iterable
    {
        yield 'no path in the URL' => ['https://example.com', null];
        yield 'parent directory in the header' => ['https://example.com/download', 'attachment; filename=".."'];
        yield 'root in the header' => ['https://example.com/download', 'attachment; filename="/"'];
    }

    private function extractFileName(string $url, ?string $contentDisposition): string
    {
        $headers = null === $contentDisposition ? [] : ['Content-Disposition' => $contentDisposition];
        $client = new MockHttpClient(new MockResponse('', ['response_headers' => $headers]));
        $downloader = new HttpDownloader($client, new Filesystem());

        return new \ReflectionMethod(HttpDownloader::class, 'extractFileName')->invoke($downloader, $client->request('GET', $url), $url);
    }
}
