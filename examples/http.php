<?php

namespace http;

use Castor\Attribute\AsTask;

use function Castor\fs;
use function Castor\http_download;
use function Castor\http_request;
use function Castor\io;

#[AsTask(description: 'Make HTTP request')]
function request(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    $response = http_request('GET', $url);

    io()->writeln($response->getContent());
}

#[AsTask(description: 'Download a file through HTTP')]
function download(): void
{
    $downloadUrl = 'http://eu-central-1.linodeobjects.com/speedtest/100MB-speedtest';

    if (isset($_SERVER['ENDPOINT'])) {
        $downloadUrl = $_SERVER['ENDPOINT'] . '/big-file.php';
    }

    $downloadedFilePath = '/tmp/castor-tests/examples/http-download-dummy-file';

    try {
        $response = http_download($downloadUrl, $downloadedFilePath, stream: false);

        io()->writeln(
            \sprintf(
                'Successfully downloaded file of size "%s" from url "%s" to "%s" with status code "%s"',
                filesize($downloadedFilePath),
                $downloadUrl,
                $downloadedFilePath,
                $response->getStatusCode()
            )
        );
    } finally {
        fs()->remove($downloadedFilePath);
    }
}
