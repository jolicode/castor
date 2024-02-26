<?php

namespace http;

use Castor\Attribute\AsTask;

use function Castor\http_download;
use function Castor\http_request;
use function Castor\http_upload;

#[AsTask(description: 'Make HTTP request')]
function request(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    $response = http_request('GET', $url);

    echo $response->getContent();
}

#[AsTask(description: 'Download a file through HTTP')]
function download(): void
{
    http_download('https://github.blog/wp-content/uploads/2023/09/Productivity-LightMode-1.png?resize=1200%2C630');
    http_download('http://releases.ubuntu.com/18.04.2/ubuntu-18.04.2-desktop-amd64.iso');
}

#[AsTask(description: 'Upload a file through HTTP')]
function upload(): void
{
    file_put_contents($filepath = sys_get_temp_dir() . '/castor_http_upload_test.txt', 'Hello World');
    $request = http_upload('https://httpbin.org/post', $filepath);

    echo $request->getContent();
}
