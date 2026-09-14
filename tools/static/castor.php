<?php

namespace castor\static;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\run;

const PHP_EXTENSIONS = 'mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2,iconv';

/** The compile options of each system a static binary is built for */
const TARGETS = [
    'linux-amd64' => ['os' => 'linux', 'arch' => 'x86_64'],
    'linux-arm64' => ['os' => 'linux', 'arch' => 'aarch64'],
    'darwin-amd64' => ['os' => 'macos', 'arch' => 'x86_64'],
    'darwin-arm64' => ['os' => 'macos', 'arch' => 'aarch64'],
];

#[AsTask(description: 'Build static binary for Linux (amd64) system')]
function linuxAmd64(): void
{
    build('linux-amd64');
}

#[AsTask(description: 'Build static binary for Linux (arm64) system')]
function linuxArm64(): void
{
    build('linux-arm64');
}

#[AsTask(description: 'Build static binary for MacOS (amd64) system')]
function darwinAmd64(): void
{
    build('darwin-amd64');
}

#[AsTask(description: 'Build static binary for MacOS (arm64) system')]
function darwinArm64(): void
{
    build('darwin-arm64');
}

#[AsTask(description: 'Print the directory where the PHP build of a system is cached (used by the CI)')]
function cacheDir(string $target): void
{
    // The cache key does not depend on the phar, any path does
    echo capture(['tests/bin/compile-get-cache-key', 'phar-location-is-not-used-in-cache-key', ...compile_options($target)]);
}

function build(string $target): void
{
    run([
        'bin/castor',
        'compile',
        "tools/phar/build/castor.{$target}.phar",
        "--binary-path=castor.{$target}",
        ...compile_options($target),
    ], context: context()->withTimeout(0));
}

/** @return list<string> */
function compile_options(string $target): array
{
    if (!isset(TARGETS[$target])) {
        throw new \InvalidArgumentException(\sprintf('Unknown target "%s", expected one of "%s".', $target, implode('", "', array_keys(TARGETS))));
    }

    return [
        '--os=' . TARGETS[$target]['os'],
        '--arch=' . TARGETS[$target]['arch'],
        '--php-extensions=' . PHP_EXTENSIONS,
    ];
}
