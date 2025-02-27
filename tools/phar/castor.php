<?php

namespace castor\phar;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Build phar for Linux system')]
function linux()
{
    compile(fn () => run('vendor/bin/box compile -c box.linux-amd64.json'));
    compile(fn () => run('vendor/bin/box compile -c box.linux-arm64.json'));
}

#[AsTask(description: 'Build phar for MacOS system')]
function darwin()
{
    compile(fn () => run('vendor/bin/box compile -c box.darwin-amd64.json'));
    compile(fn () => run('vendor/bin/box compile -c box.darwin-arm64.json'));
}

#[AsTask(description: 'Build phar for Windows system')]
function windows()
{
    compile(fn () => run('vendor/bin/box compile -c box.windows-amd64.json'));
}

#[AsTask(description: 'Build phar for all systems')]
function build()
{
    parallel(linux(...), darwin(...), windows(...));
}

#[AsTask(description: 'install dependencies')]
function install(): void
{
    run(['composer', 'install']);
}

#[AsTask(description: 'update dependencies')]
function update(): void
{
    io()->section('Update phar dependencies');
    run(['composer', 'update']);
}

function compile(callable $compiler)
{
    // When we compile the phar, we use the current castor application, with its autoloader.
    // It has a name, like  `ComposerAutoloaderInit2a521a46f932896859028f670feabe03`.
    // So in the phar, we will ship an autoloader named the same.

    // Then if we use this phar, in the current castor application, castor will try to
    // load **again** an autoloader with the very same name. Guess what? It will fail.

    // So we force a name when we compile the phar. It can be static since it
    // could not conflict with a real autoloader (in a client application).
    // Except if the application choses the very same name... but it's unlikely.

    $composerFile = __DIR__ . '/../../composer.json';
    $composerJson = file_get_contents($composerFile);
    $composerData = json_decode($composerJson, true);
    $composerData['config']['autoloader-suffix'] = 'CastorPharb0674093dafe41cab39902efe0941c3f';

    try {
        file_put_contents($composerFile, json_encode($composerData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        $compiler();
    } finally {
        file_put_contents($composerFile, $composerJson);
    }
}
