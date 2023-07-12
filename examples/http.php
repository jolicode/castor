<?php

use Castor\Attribute\AsTask;

use function Castor\http;

#[AsTask(description: 'Make HTTP request')]
function httpRequest(): void
{
    $response = http()->request('GET', 'https://api.github.com/repos/jolicode/castor');

    echo $response->toArray()['html_url'];
}
