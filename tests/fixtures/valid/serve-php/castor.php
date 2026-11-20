<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\run;
use function Castor\serve_php;

#[AsTask(description: 'Start the web interface and print its URL')]
function web(): void
{
    serve_php(__DIR__ . '/web');
}

#[AsTask]
function web_with_callback(): void
{
    serve_php(__DIR__ . '/web', static function (string $url): void {
        run(['echo', "URL: {$url}"]);
    });
}

#[AsTask]
function web_without_document_root(): void
{
    serve_php(__DIR__ . '/missing');
}
