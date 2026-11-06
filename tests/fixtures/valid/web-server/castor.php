<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

// Keep in sync with doc/docs/going-further/extending-castor/compile.md
#[AsTask(description: 'Start the web interface and print its URL')]
function web(): void
{
    // Neither a binary nor a docroot can be used from inside the phar
    $directory = sys_get_temp_dir() . '/my-app-' . bin2hex(random_bytes(8));
    fs()->mkdir($directory, 0o700);
    fs()->mirror(__DIR__ . '/web', "{$directory}/web");

    // PHP_BINARY is empty in a static binary
    $php = \PHP_BINARY;
    if ('' === $php) {
        $php = "{$directory}/php";
        fs()->copy(Phar::running() . '/.castor-php-cli/php', $php);
        fs()->chmod($php, 0o755);
    }

    $url = null;

    run(
        [$php, '-S', '127.0.0.1:0', '-t', "{$directory}/web"],
        context: context()
            ->withEnvironment(['PHP_CLI_SERVER_WORKERS' => '4'])
            // CTRL+C stops the server, not the task: it still has to clean up
            ->withTrappedSignals()
            ->withAllowFailure()
            ->withTimeout(null)
            ->withPty(false)
            ->withTty(false)
            ->withQuiet(),
        callback: static function (string $type, string $bytes) use (&$url): void {
            // Each worker announces itself
            if (null === $url && preg_match('{Development Server \((http://[^)]+)\) started}', $bytes, $matches)) {
                $url = $matches[1];
                io()->writeln("URL: {$url}");
            }
        },
    );

    fs()->remove($directory);
}
