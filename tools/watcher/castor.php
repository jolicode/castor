<?php

namespace castor\watcher;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Build watcher for Linux system')]
function linux()
{
    run('go build -o bin/watcher-linux-amd64 -ldflags="-s -w" main.go', environment: ['GOOS' => 'linux', 'GOARCH' => 'amd64', 'CGO_ENABLED' => '0']);
    run('upx --brute bin/watcher-linux-amd64');

    run('go build -o bin/watcher-linux-arm64 -ldflags="-s -w" main.go', environment: ['GOOS' => 'linux', 'GOARCH' => 'arm64', 'CGO_ENABLED' => '0']);
    run('upx --brute bin/watcher-linux-arm64');
}

#[AsTask(description: 'Build watcher for MacOS system')]
function darwin()
{
    run('go build -o bin/watcher-darwin-amd64 -ldflags="-s -w" main.go', environment: ['GOOS' => 'darwin', 'GOARCH' => 'amd64', 'CGO_ENABLED' => '0']);
    run('go build -o bin/watcher-darwin-arm64 -ldflags="-s -w" main.go', environment: ['GOOS' => 'darwin', 'GOARCH' => 'arm64', 'CGO_ENABLED' => '0']);
}

#[AsTask(description: 'Build watcher for Windows system')]
function windows()
{
    run('go build -o bin/watcher-windows.exe -ldflags="-s -w" main.go', environment: ['GOOS' => 'windows', 'CGO_ENABLED' => '0']);
}

#[AsTask(description: 'Build watcher for all systems')]
function build()
{
    parallel(fn () => linux(), fn () => darwin(), fn () => windows());
}
