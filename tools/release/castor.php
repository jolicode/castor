<?php

namespace castor\release;

use Castor\Attribute\AsTask;
use Castor\Console\Application;
use Castor\Exception\LlmException;
use Castor\Exception\ProblemException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\check;
use function Castor\context;
use function Castor\exit_code;
use function Castor\finder;
use function Castor\fs;
use function Castor\io;
use function Castor\llm_ask;
use function Castor\run;

const REPO = 'jolicode/castor';
// 10 binaries and their SHA256SUMS file
const EXPECTED_ARTIFACTS = 11;

#[AsTask(description: 'Release a new version of castor', aliases: ['release'])]
function release(): int
{
    io()->title('Release a new version of Castor');

    io()->section('Check requirements');

    check(
        'Check if Git is installed',
        'Git is not installed. Please install it before.',
        static fn () => new ExecutableFinder()->find('git'),
    );

    check(
        'Check if GitHub CLI is installed',
        'GitHub CLI is not installed. Please install it before.',
        static fn () => new ExecutableFinder()->find('gh'),
    );

    check(
        'Check if there are uncommitted changes',
        'You have uncommitted changes. Please commit or stash them before.',
        static fn () => !capture(['git', 'status', '--porcelain']),
    );

    $currentSha = capture('git rev-parse HEAD');
    io()->comment("Commit: <comment>{$currentSha}</comment>");

    check(
        'Check current commit exist on remote',
        'You are not up to date with origin/main. Please push the latest changes before.',
        static fn () => $currentSha === capture('git ls-remote git@github.com:' . REPO . ' refs/heads/main | cut -f 1'),
    );

    $version = Application::VERSION;
    io()->comment("Version: <comment>{$version}</comment>");

    check(
        'Check if a tag already exists for this version',
        "Version {$version} already exists. Change the version in `src/Console/Application.php`.",
        static fn () => !capture('git ls-remote git@github.com:' . REPO . " refs/tags/{$version}", context: context()->withAllowFailure()),
    );

    $changelog = changelogSection($version);

    check(
        'Check if the CHANGELOG has a section for this version',
        "The CHANGELOG has no section for version {$version}. Add it before.",
        static fn () => null !== $changelog,
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
        static fn () => run(['gh', 'run', 'download', $runArtifacts['databaseId'], '--dir', $artifactsDir], context: context()->withQuiet())->isSuccessful(),
    );

    $files = finder()
        ->in($artifactsDir)
        ->files()
    ;

    check(
        'Check the number of artifacts',
        'There are not enough files in the artifacts directory.',
        static fn () => EXPECTED_ARTIFACTS === \count($files),
    );

    check(
        'Check if artifacts are not empty',
        'Some artifacts are empty.',
        static fn () => !array_filter(
            iterator_to_array($files),
            // A least 3MB, except the checksums file
            static fn (SplFileInfo $file) => 'SHA256SUMS' !== $file->getFilename() && 3_000_000 > $file->getSize()
        ),
    );

    check(
        'Check the artifacts checksums',
        'The artifacts do not match their SHA256SUMS file.',
        // `gh run download` extracts each artifact in a directory of its own name
        static fn () => 0 === exit_code(['sha256sum', '--check', '--quiet'], context: context()->withWorkingDirectory($artifactsDir)->withQuiet()->withInput(
            preg_replace('/^(\S+)  (.+)$/m', '$1  $2/$2', file_get_contents($artifactsDir . '/SHA256SUMS/SHA256SUMS')),
        )),
    );

    io()->section('Write the release notes');

    // Outside of the artifacts directory, whose files are all attached to the release
    $notesFile = __DIR__ . '/release-notes.md';
    $notes = releaseNotes($version, $currentSha, $changelog);
    fs()->dumpFile($notesFile, $notes);

    io()->writeln(OutputFormatter::escape($notes));
    io()->newLine();

    if (!io()->confirm("Publish the release with these notes? You can edit them in {$notesFile} before answering.")) {
        throw new ProblemException('The release has not been published.');
    }

    io()->write('Publishing the release');

    $process = run(
        [
            'gh', 'release', 'create', $version,
            '--title', "Release {$version}",
            '--notes-file', $notesFile,
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
        // Anything but a success (failure, cancelled, timed_out...) must not be released
        if ('success' !== $run['conclusion']) {
            run(['gh', 'run', 'view', $run['databaseId']]);

            throw new ProblemException("The CI {$run['name']} has not succeeded (conclusion: {$run['conclusion']}). Please fix it before releasing.");
        }
    } else {
        $process = run(['gh', 'run', 'watch', $run['databaseId'], '--exit-status'], context: context()->toInteractive());

        if (!$process->isSuccessful()) {
            throw new ProblemException("The CI {$run['name']} has failed. Please fix it before releasing.");
        }
    }

    io()->comment("{$run['name']}'s run is <info>okay</info>!");
}

function changelogSection(string $version): ?string
{
    // The section of "v1.9.0" starts with "## 1.9.0 (2026-10-13)"
    $pattern = '{^## ' . preg_quote(ltrim($version, 'v')) . ' .*?(?=^## |\z)}ms';

    if (!preg_match($pattern, file_get_contents(__DIR__ . '/../../CHANGELOG.md'), $matches)) {
        return null;
    }

    return trim($matches[0]);
}

/**
 * Sorts the notes generated by GitHub, one line per pull request, under the
 * headings of our previous releases.
 */
function releaseNotes(string $version, string $sha, string $changelog): string
{
    $previousVersion = capture(['gh', 'api', 'repos/' . REPO . '/releases/latest', '--jq', '.tag_name']);
    $generatedNotes = capture([
        'gh', 'api', 'repos/' . REPO . '/releases/generate-notes',
        '-f', "tag_name={$version}",
        '-f', "target_commitish={$sha}",
        '-f', "previous_tag_name={$previousVersion}",
        '--jq', '.body',
    ]);

    $prompt = <<<PROMPT
        You write the notes of the {$version} release of Castor, a task runner written in PHP.

        Here are the notes generated by GitHub, with one line per pull request merged since {$previousVersion}:

        <generated-notes>
        {$generatedNotes}
        </generated-notes>

        Here is the CHANGELOG of this release, written for the users of Castor:

        <changelog>
        {$changelog}
        </changelog>

        Sort the lines of the pull requests under these headings, in this order, and drop the headings without any line:

        - `### Features`: new features and changes of behavior;
        - `### Security`: hardening and fixes of vulnerabilities;
        - `### Fixes`: bug fixes;
        - `### Documentation`: changes of the documentation only;
        - `### Internal`: everything the users of Castor do not see, like the CI, the tests, the tooling, the updates of the dependencies and the preparation of the release.

        Choose the heading of a pull request from the section of the CHANGELOG describing the same change. A pull request missing from the CHANGELOG usually belongs to Documentation or Internal.

        Keep each line exactly as it is written, unless you group it with others: the lines of pull requests about the same change, like a feature and the fixes of that feature, can be grouped into one line with a title describing the change, all their authors and all their links, like `* <title> by @author1 and @author2 in <link 1> and <link 2>`.
        Keep the `## What's Changed` heading, the `## New Contributors` section and the `**Full Changelog**` line as they are.
        The notes must look like this:

        ## What's Changed

        ### Features

        * <line of a pull request>
        * <line of a pull request>

        ### Fixes

        * <line of a pull request>

        ## New Contributors
        * <line of a new contributor>

        **Full Changelog**: <link>

        Answer with the release notes only, in Markdown, without any introduction nor code fence.
        PROMPT;

    try {
        $notes = llm_ask($prompt);
    } catch (LlmException $e) {
        io()->warning("Could not sort the release notes with a LLM, sort the notes generated by GitHub by hand instead: {$e->getMessage()}");

        return $generatedNotes;
    }

    // A line of a pull request lost or invented by the LLM
    $pullRequestPattern = '{https://github\.com/' . preg_quote(REPO) . '/pull/\d+}';
    preg_match_all($pullRequestPattern, $generatedNotes, $expected);
    preg_match_all($pullRequestPattern, $notes, $actual);
    if ($missing = array_diff($expected[0], $actual[0])) {
        io()->warning('The LLM dropped these pull requests, add them back: ' . implode(', ', array_unique($missing)));
    }
    if ($unknown = array_diff($actual[0], $expected[0])) {
        io()->warning('The LLM added these pull requests, remove them: ' . implode(', ', array_unique($unknown)));
    }

    return $notes;
}
