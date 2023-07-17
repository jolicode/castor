<?php

namespace http;

use Castor\Attribute\AsTask;
use function Castor\http;

#[AsTask(description: 'get httpbin status code')]
function status(): void
{
    $response = http()->request('GET', 'https://httpbin.org/status/418');

    echo "{$response->getStatusCode()}\n";
}
