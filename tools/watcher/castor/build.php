<?php

namespace castor\watcher;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Build watcher for Linux system')]
function linux()
{
    run('go build -o bin/watcher-linux -ldflags="-s -w" main.go', environment: ['GOOS' => 'linux', 'CGO_ENABLED' => '0'], path: __DIR__ . '/..');
    run('upx --brute bin/watcher-linux', path: __DIR__ . '/..');
}

#[AsTask(description: 'Build watcher for MacOS system')]
function darwin()
{
    run('go build -o bin/watcher-darwin -ldflags="-s -w" main.go', environment: ['GOOS' => 'darwin', 'CGO_ENABLED' => '0'], path: __DIR__ . '/..');
}

#[AsTask(description: 'Build watcher for Windows system')]
function windows()
{
    run('go build -o bin/watcher-windows.exe -ldflags="-s -w" main.go', environment: ['GOOS' => 'windows', 'CGO_ENABLED' => '0'], path: __DIR__ . '/..');
}

#[AsTask(description: 'Build watcher for all systems')]
function build()
{
    parallel(fn () => linux(), fn () => darwin(), fn () => windows());
}
