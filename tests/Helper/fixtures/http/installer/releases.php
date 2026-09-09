<?php

// Fake GitHub releases used by InstallerTest. The installer downloads
// "<releases>/download/<version>/<asset>" or "<releases>/latest/download/<asset>":
// v9.0.0 (the latest) has a matching SHA256SUMS file, v9.0.1 a wrong one, and
// v8.0.0 none, like the releases published before that file existed. The
// "binary" is a shell script answering to --version.

$path = $_SERVER['PATH_INFO'] ?? '';

if (!preg_match('#^/(?:latest/download|download/(?<version>[^/]+))/(?<asset>[^/]+)$#', $path, $matches)) {
    http_response_code(404);

    exit;
}

$version = $matches['version'] ?: 'v9.0.0';
$asset = $matches['asset'];

if (!\in_array($version, ['v9.0.0', 'v9.0.1', 'v8.0.0'], true)) {
    http_response_code(404);

    exit;
}

$binary = "#!/bin/sh\necho \"castor {$version}\"\n";

if ('SHA256SUMS' === $asset) {
    if ('v8.0.0' === $version) {
        http_response_code(404);

        exit;
    }

    $checksum = 'v9.0.1' === $version ? str_repeat('0', 64) : hash('sha256', $binary);

    header('Content-Type: text/plain');
    foreach (['linux-amd64', 'linux-arm64', 'darwin-amd64', 'darwin-arm64'] as $platform) {
        echo "{$checksum}  castor.{$platform}\n";
        echo "{$checksum}  castor.{$platform}.phar\n";
    }
    echo "{$checksum}  castor.windows-amd64.phar\n";

    exit;
}

if (preg_match('/^castor\.(linux|darwin)-(amd64|arm64)(\.phar)?$/', $asset)) {
    header('Content-Type: application/octet-stream');
    echo $binary;

    exit;
}

http_response_code(404);
