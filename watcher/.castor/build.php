<?php

use Castor\Attribute\Task;

use function Castor\cd;
use function Castor\exec;
use function Castor\parallel;

#[Task(description: 'Build watcher for unix system', namespace: 'watcher')]
function unix()
{
    cd(__DIR__ . \DIRECTORY_SEPARATOR . '..');
    exec('go build -o bin/watcher -ldflags="-s -w" main.go', environment: ['GOOS' => 'linux', 'CGO_ENABLED' => '0']);
    exec('upx --brute bin/watcher');
}

#[Task(description: 'Build watcher for unix system', namespace: 'watcher')]
function windows()
{
    cd(__DIR__ . \DIRECTORY_SEPARATOR . '..');
    exec('go build -o bin/watcher.exe -ldflags="-s -w" main.go', environment: ['GOOS' => 'windows', 'CGO_ENABLED' => '0']);
}

#[Task(description: 'Build watcher for all system', namespace: 'watcher')]
function build()
{
    parallel(fn () => unix(), fn () => windows());
}
