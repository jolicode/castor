<?php

use Castor\Attribute\AsTask;

use function Castor\request;

#[AsTask(description: 'Make HTTP request')]
function httpRequest(): void
{
    $url = $_SERVER['ENDPOINT'] ?? 'https://example.com';

    $response = request('GET', $url);

    echo $response->getContent();
}
