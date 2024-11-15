<?php

namespace castor\release;

use Castor\Attribute\AsTask;
use Castor\Console\Application;
use Castor\Exception\ProblemException;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\check;
use function Castor\context;
use function Castor\finder;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

const REPO = 'jolicode/castor';
const EXPECTED_ARTIFACTS = 8;

#[AsTask(description: 'Release a new version of castor', aliases: ['release'])]
function release(): int
{
    io()->title('Release a new version of Castor');

    io()->section('Check requirements');

    check(
        'Check if Git is installed',
        'Git is not installed. Please install it before.',
        fn () => (new ExecutableFinder())->find('git'),
    );

    check(
        'Check if GitHub CLI is installed',
        'GitHub CLI is not installed. Please install it before.',
        fn () => (new ExecutableFinder())->find('gh'),
    );

    check(
        'Check if there are uncommitted changes',
        'You have uncommitted changes. Please commit or stash them before.',
        fn () => !capture(['git', 'status', '--porcelain']),
    );

    $currentSha = capture('git rev-parse HEAD');
    io()->comment("Commit: <comment>{$currentSha}</comment>");

    check(
        'Check current commit exist on remote',
        'You are not up to date with origin/main. Please push the latest changes before.',
        fn () => $currentSha === capture('git ls-remote git@github.com:' . REPO . ' refs/heads/main | cut -f 1'),
    );

    $version = Application::VERSION;
    io()->comment("Version: <comment>{$version}</comment>");

    check(
        'Check if a tag already exists for this version',
        "Version {$version} already exists. Change the version in `src/Console/Application.php`.",
        fn () => !capture('git ls-remote git@github.com:' . REPO . " refs/tags/{$version}", context: context()->withAllowFailure()),
    );

    io()->section('Check GitHub Actions requirements');

    $response = capture(['gh', 'run', 'list', '--commit', $currentSha, '--json', 'name,databaseId,status,conclusion']);
    if (!$response) {
        throw new ProblemException('Could not found a GitHub Actions run for this commit. Please wait for the CI to start.');
    }

    $runs = json_decode($response, true);
    $runTest = null;
    $runArtifacts = null;
    foreach ($runs as $run) {
        if ('Continuous Integration' === $run['name']) {
            $runTest = $run;
        }
        if ('Artifacts' === $run['name']) {
            $runArtifacts = $run;
        }
    }
    if (!$runTest || !$runArtifacts) {
        throw new ProblemException('Could not found a GitHub Actions run for this commit. Please wait for the CI to start.');
    }

    checkCi($runArtifacts);
    checkCi($runTest);

    io()->section("Releasing version {$version}");

    $artifactsDir = __DIR__ . '/artifacts';
    fs()->remove($artifactsDir);
    fs()->mkdir($artifactsDir);

    check(
        'Download artifacts',
        'Failed to download artifacts.',
        fn () => run(['gh', 'run', 'download', $runArtifacts['databaseId'], '--dir', $artifactsDir], context: context()->withQuiet())->isSuccessful(),
    );

    $files = finder()
        ->in($artifactsDir)
        ->files()
    ;

    check(
        'Check the number of artifacts',
        'There are not enough files in the artifacts directory.',
        fn () => EXPECTED_ARTIFACTS === \count($files),
    );

    check(
        'Check if artifacts are not empty',
        'Some artifacts are empty.',
        fn () => !array_filter(
            iterator_to_array($files),
            // A least 3MB
            fn (SplFileInfo $file) => 3_000_000 > $file->getSize()
        ),
    );

    io()->write('Publishing the release');

    $process = run(
        [
            'gh', 'release', 'create', $version,
            '--title', "Release {$version}",
            ...array_keys(iterator_to_array($files)),
        ],
        context: context()->toInteractive()
    );

    if (!$process->isSuccessful()) {
        throw new ProblemException('Failed to publish the release.');
    }

    io()->success('Release published!');

    return 0;
}

function checkCi(array $run): void
{
    io()->comment("{$run['name']}'s run id <comment>{$run['databaseId']}</comment>");

    if ('completed' === $run['status']) {
        if ('failure' === $run['conclusion']) {
            run(['gh', 'run', 'view', $run['databaseId']]);

            throw new ProblemException("The CI {$run['name']} has failed. Please fix it before releasing.");
        }
    } else {
        $process = run(['gh', 'run', 'watch', $run['databaseId'], '--exit-status'], context: context()->toInteractive());

        if (!$process->isSuccessful()) {
            throw new ProblemException("The CI {$run['name']} has failed. Please fix it before releasing.");
        }
    }

    io()->comment("{$run['name']}'s run is <info>okay</info>!");
}
