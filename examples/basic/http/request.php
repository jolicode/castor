<?php

namespace http;

use Castor\Attribute\AsTask;

use function Castor\http_request;
use function Castor\io;

#[AsTask(description: 'Make HTTP request')]
function request(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    $response = http_request('GET', $url);

    io()->writeln($response->getContent());
}
