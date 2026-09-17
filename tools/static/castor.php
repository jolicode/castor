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
    'windows-amd64' => ['os' => 'windows', 'arch' => 'x86_64'],
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

#[AsTask(description: 'Build static binary for Windows (amd64) system')]
function windowsAmd64(): void
{
    build('windows-amd64');
}

#[AsTask(description: 'Print the directory where the PHP build of a system is cached (used by the CI)')]
function cacheDir(string $target): void
{
    // The cache key does not depend on the phar, any path does. The script is
    // run through PHP: on Windows, cmd.exe cannot run its shebang.
    echo capture([\PHP_BINARY, 'tests/bin/compile-get-cache-key', 'phar-location-is-not-used-in-cache-key', ...compile_options($target)]), \PHP_EOL;
}

function build(string $target): void
{
    $windows = 'windows' === TARGETS[$target]['os'];

    run([
        // On Windows, cmd.exe cannot run the bin/castor shebang script. Only
        // this file is loaded: the examples imported by the root castor.php
        // download remote packages, and do not boot on Windows
        ...($windows ? ['php'] : []),
        'bin/castor',
        '--castor-file=' . __FILE__,
        'compile',
        "tools/phar/build/castor.{$target}.phar",
        "--binary-path=castor.{$target}" . ($windows ? '.exe' : ''),
        ...compile_options($target),
    ], context: context()->withTimeout(0));
}

/** @return list<string> */
function compile_options(string $target): array
{
    if (!isset(TARGETS[$target])) {
        throw new \InvalidArgumentException(\sprintf('Unknown target "%s", expected one of "%s".', $target, implode('", "', array_keys(TARGETS))));
    }

    $extensions = explode(',', PHP_EXTENSIONS);
    if ('windows' === TARGETS[$target]['os']) {
        // The posix and pcntl extensions do not exist on Windows
        $extensions = array_diff($extensions, ['posix', 'pcntl']);
    }

    return [
        '--os=' . TARGETS[$target]['os'],
        '--arch=' . TARGETS[$target]['arch'],
        '--php-extensions=' . implode(',', $extensions),
    ];
}
