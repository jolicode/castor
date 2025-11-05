<?php

namespace castor\mkdocs;

use Castor\Attribute\AsTask;
use Castor\Console\Application;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\docker\docker_run;
use function Castor\exit_code;
use function Castor\fs;
use function Castor\http_download;
use function Castor\io;
use function Castor\run;

const DOCKER_IMAGE_NAME = 'castor-mkdocs';

#[AsTask(description: 'Build mkdocs docker image')]
function docker_build(): int
{
    return exit_code(\sprintf(
        'docker build -t %s %s',
        DOCKER_IMAGE_NAME,
        __DIR__,
    ));
}

#[AsTask(description: 'Fetch external assets')]
function fetch_assets(): void
{
    io()->title('Fetching external assets for MkDocs documentation');

    http_download('https://raw.githubusercontent.com/jolicode/oss-theme/refs/heads/main/MkDocs/extra.css', __DIR__ . '/../../doc/assets/stylesheets/jolicode.css');
    http_download('https://raw.githubusercontent.com/jolicode/oss-theme/refs/heads/main/snippet-joli-footer.html', __DIR__ . '/overrides/partials/jolicode-footer.html');

    $html = <<<'HTML'
        Castor is licensed under
        <a href="https://github.com/jolicode/castor/blob/main/LICENSE" target="_blank" rel="noreferrer noopener" class="jf-link">
          MIT license
        </a>
        HTML;

    $footer = file_get_contents(__DIR__ . '/overrides/partials/jolicode-footer.html');
    $footer = str_replace('#GITHUB_REPO', 'jolicode/castor', $footer);
    $footer = str_replace('<!-- #SUBTITLE -->', $html, $footer);

    file_put_contents(__DIR__ . '/overrides/partials/jolicode-footer.html', $footer);
}

#[AsTask(description: 'Build documentation')]
function build(): void
{
    fetch_assets();

    io()->title('Building MkDocs documentation');

    do_run(['mkdocs', 'build']);

    $installerPath = __DIR__ . '/../../installer/bash-installer';

    if (fs()->exists($installerPath)) {
        fs()->copy($installerPath, __DIR__ . '/site/install');
    } else {
        io()->error(\sprintf('Bash installer file not found in %s', $installerPath));
    }
}

#[AsTask(description: 'Serve documentation and watches for changes')]
function serve(): void
{
    fetch_assets();

    io()->title('Building and watching MkDocs documentation');

    do_run(['mkdocs', 'serve']);
}

function do_run(array $runCommand, ?Context $c = null): Process
{
    $process = run(\sprintf(
        'docker image inspect %s',
        DOCKER_IMAGE_NAME,
    ), context: context()->withAllowFailure(true)->withQuiet(true));

    if (false === $process->isSuccessful()) {
        run(\sprintf(
            'docker build -t %s %s',
            DOCKER_IMAGE_NAME,
            __DIR__,
        ));
    }

    return docker_run(
        DOCKER_IMAGE_NAME,
        $runCommand,
        volumes: [
            \sprintf('%s:/mkdocs:cached', realpath(__DIR__)),
            \sprintf('%s:/mkdocs/CHANGELOG.md:cached', realpath(__DIR__) . '/../../CHANGELOG.md'),
            \sprintf('%s:/mkdocs/doc:cached', realpath(__DIR__ . '/../../doc')),
        ],
        environment: [
            'CASTOR_VERSION' => Application::VERSION,
        ],
        context: $c
    );
}
