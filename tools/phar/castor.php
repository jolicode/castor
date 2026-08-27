<?php

namespace castor\phar;

use Castor\Attribute\AsTask;
use Castor\Console\Application;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Build phar for Linux system')]
function linux(): void
{
    compile(static fn () => run('vendor/bin/box compile -c box.linux-amd64.json'));
    compile(static fn () => run('vendor/bin/box compile -c box.linux-arm64.json'));
}

#[AsTask(description: 'Build phar for MacOS system')]
function darwin(): void
{
    compile(static fn () => run('vendor/bin/box compile -c box.darwin-amd64.json'));
    compile(static fn () => run('vendor/bin/box compile -c box.darwin-arm64.json'));
}

#[AsTask(description: 'Build phar for Windows system')]
function windows(): void
{
    compile(static fn () => run('vendor/bin/box compile -c box.windows-amd64.json'));
}

#[AsTask(description: 'Build phar for all systems')]
function build(): void
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

function compile(callable $compiler): void
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

    // A build that is not a release gets a snapshot version, like "v1.7.0-14-g4531440"
    $applicationFile = __DIR__ . '/../../src/Console/Application.php';
    $applicationSource = file_get_contents($applicationFile);
    $snapshotVersion = snapshot_version();

    try {
        file_put_contents($composerFile, json_encode($composerData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        if ($snapshotVersion) {
            io()->comment("Building a snapshot, version {$snapshotVersion}");
            file_put_contents($applicationFile, str_replace(
                "public const VERSION = '" . Application::VERSION . "';",
                "public const VERSION = '{$snapshotVersion}';",
                $applicationSource,
            ));
        }
        $compiler();
    } finally {
        file_put_contents($composerFile, $composerJson);
        file_put_contents($applicationFile, $applicationSource);
    }
}

/**
 * The release commit is built before its tag is created (see tools/release),
 * so as long as Application::VERSION is not tagged, this is a release build
 * that keeps its version. Once the tag exists, every later commit is a
 * snapshot, versioned by git describe: last release, number of commits since,
 * and commit hash.
 */
function snapshot_version(): ?string
{
    $context = context()->withQuiet()->withAllowFailure();

    if (!run(['git', 'rev-parse', '-q', '--verify', 'refs/tags/' . Application::VERSION], context: $context)->isSuccessful()) {
        return null;
    }

    return trim(capture(['git', 'describe', '--tags', '--match', 'v*'], context: $context)) ?: null;
}
