<?php

use Castor\Attribute\Task;
use Castor\Context;

use function Castor\exec;
use function Castor\parallel;

#[Task(description: 'Build watcher for Unix system', namespace: 'watcher')]
function unix(Context $c)
{
    $c = $c->withCd(__DIR__ . '/..');
    exec('go build -o bin/watcher -ldflags="-s -w" main.go', environment: ['GOOS' => 'linux', 'CGO_ENABLED' => '0'], context: $c);
    exec('upx --brute bin/watcher', context: $c);
}

#[Task(description: 'Build watcher for Windows system', namespace: 'watcher')]
function windows(Context $c)
{
    $c = $c->withCd(__DIR__ . '/..');
    exec('go build -o bin/watcher.exe -ldflags="-s -w" main.go', environment: ['GOOS' => 'windows', 'CGO_ENABLED' => '0'], context: $c);
}

#[Task(description: 'Build watcher for all systems', namespace: 'watcher')]
function build(Context $c)
{
    parallel(fn () => unix($c), fn () => windows($c));
}
