<?php

require __DIR__ . '/../vendor/autoload.php';

// The application factory does this when castor boots, which the unit tests never do.
Castor\Helper\PathHelper::initialize(
    \dirname(__DIR__),
    \dirname(__DIR__) . '/' . Castor\Import\Remote\Composer::VENDOR_DIR,
    \dirname(__DIR__),
);

putenv('COMPOSER_VENDOR_DIR');
unset($_SERVER['COMPOSER_VENDOR_DIR'], $_ENV['COMPOSER_VENDOR_DIR']);

// Locally, reuse the token of an authenticated `gh` CLI so the tests hitting
// the GitHub API (see RepackHelper, and spc through CompileCommand) don't hit
// the anonymous rate limit. In CI, GITHUB_TOKEN is already provided by the
// workflow. putenv() (not just $_SERVER) is needed so Symfony Process
// forwards it to child processes that don't set an explicit env.
if (!($_SERVER['GITHUB_TOKEN'] ?? false) && !getenv('GITHUB_TOKEN')) {
    $gh = new Symfony\Component\Process\ExecutableFinder()->find('gh');

    if (null !== $gh) {
        $process = new Symfony\Component\Process\Process([$gh, 'auth', 'token']);
        $process->run();

        if ($process->isSuccessful()) {
            $token = trim($process->getOutput());
            $_SERVER['GITHUB_TOKEN'] = $token;
            putenv('GITHUB_TOKEN=' . $token);
        }
    }
}
