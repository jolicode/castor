<?php

use Castor\Attribute\AsTask;

use function Castor\request;

#[AsTask(description: 'Make HTTP request')]
function httpRequest(): void
{
    $response = request('GET', 'https://api.github.com/repos/jolicode/castor');

    echo $response->toArray()['html_url'];
}
