<?php

namespace castor\mkdocs;

use Castor\Attribute\AsTask;
use Castor\Console\Application;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\capture;
use function Castor\context;
use function Castor\docker\docker_run;
use function Castor\exit_code;
use function Castor\fs;
use function Castor\http_download;
use function Castor\io;
use function Castor\run;
use function Castor\slug;

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

#[AsTask(description: 'Fetch external assets and build command help files')]
function build_assets(): void
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

    io()->title('Building command help files for MkDocs documentation');

    $commands = [
        'castor:repack',
        'castor:compile',
    ];

    fs()->mkdir(__DIR__ . '/build');

    foreach ($commands as $command) {
        fs()->dumpFile(\sprintf('%s/build/command_%s.md', __DIR__, slug($command)), get_command_markdown($command));
    }
}

#[AsTask(description: 'Build documentation')]
function build(): void
{
    build_assets();

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
    build_assets();

    io()->title('Building and watching MkDocs documentation');

    do_run(['mkdocs', 'serve', '--livereload']);
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
            \sprintf('%s:/mkdocs/README.md:cached', realpath(__DIR__) . '/../../README.md'),
            \sprintf('%s:/mkdocs/doc:cached', realpath(__DIR__ . '/../../doc')),
            \sprintf('%s:/examples:cached', realpath(__DIR__ . '/../../examples')),
            \sprintf('%s:/build:cached', realpath(__DIR__ . '/build')),
        ],
        environment: [
            'CASTOR_VERSION' => Application::VERSION,
        ],
        context: $c
    );
}

function get_command_markdown(string $command): string
{
    $commandMarkdown = capture(\sprintf('bin/castor help %s --format=md', $command));
    $commandJson = json_decode(capture(\sprintf('bin/castor help %s --format=json', $command)), true, 512, \JSON_THROW_ON_ERROR);

    $usage = $commandJson['usage'][0];

    // Clean usage chapter to only keep the real command with arguments/options and ignore aliases
    // Also, wrap it in a bash code block
    $commandMarkdown = preg_replace('/### Usage\n([^#]*)\n\n###/s', \sprintf("### Usage\n\n```bash\n%s\n```\n\n###", $usage), $commandMarkdown);

    // Remove options that are global to all commands
    preg_match_all('/#### `([^`]*)`/', $commandMarkdown, $matches);

    foreach ($matches[1] as $optionChapter) {
        $options = explode('|', $optionChapter);

        foreach ($options as $option) {
            $option = trim($option);

            // Not an option
            if (!str_starts_with($option, '--') && !str_starts_with($option, '-')) {
                continue 2;
            }

            // Option is used in the usage, keep it
            if (preg_match('/\[' . preg_quote($option) . '[^\]]*\]/', $usage)) {
                continue 2;
            }
        }

        // Remove the whole option chapter
        $commandMarkdown = preg_replace('/#### `' . preg_quote($optionChapter, '/') . '`\n([^#]*)/s', '', $commandMarkdown);
    }

    return $commandMarkdown;
}
