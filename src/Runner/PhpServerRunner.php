<?php

namespace Castor\Runner;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Exception\ProblemException;
use JoliCode\PhpOsHelper\OsHelper;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
final readonly class PhpServerRunner
{
    public const PHP_CLI_DIRECTORY = '.castor-php-cli';

    // \SIGINT and \SIGTERM need the pcntl extension. With their values, Castor
    // warns when it is missing
    private const TRAPPED_SIGNALS = [2, 15];

    public function __construct(
        private ContextRegistry $contextRegistry,
        private ProcessRunner $processRunner,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @param callable(string): void $onStart
     */
    public function serve(string $documentRoot, callable $onStart, ?Context $context = null): void
    {
        $context ??= $this->contextRegistry->getCurrentContext();

        // Neither a binary nor a document root can be used from inside a phar
        $extractionDirectory = sys_get_temp_dir() . '/castor-php-server-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($extractionDirectory, 0o700);

        $url = null;

        try {
            $process = $this->processRunner->run(
                [$this->getPhpCli($extractionDirectory), '-S', '127.0.0.1:0', '-t', $this->getDocumentRoot($documentRoot, $extractionDirectory)],
                context: $context
                    ->withEnvironment(['PHP_CLI_SERVER_WORKERS' => '4'])
                    // CTRL+C stops the server, not the task: it still has to clean up
                    ->withTrappedSignals(self::TRAPPED_SIGNALS)
                    ->withAllowFailure()
                    ->withTimeout(null)
                    ->withPty(false)
                    ->withTty(false)
                    ->withQuiet(),
                callback: static function (string $type, string $bytes) use (&$url, $onStart): void {
                    // Each worker announces itself
                    if (null === $url && preg_match('{Development Server \((http://[^)]+)\) started}', $bytes, $matches)) {
                        $url = $matches[1];
                        $onStart($url);
                    }
                },
            );
        } finally {
            $this->filesystem->remove($extractionDirectory);
        }

        if (null === $url) {
            throw new ProblemException(\sprintf('The PHP web server could not start: %s', trim($process->getErrorOutput() . $process->getOutput())));
        }
    }

    private function getPhpCli(string $extractionDirectory): string
    {
        if (!get_cfg_var('castor.static')) {
            return \PHP_BINARY;
        }

        $binary = OsHelper::isWindows() ? 'php.exe' : 'php';
        $embeddedPhpCli = \Phar::running() . '/' . self::PHP_CLI_DIRECTORY . '/' . $binary;

        if (!is_file($embeddedPhpCli)) {
            throw new ProblemException('This static binary cannot start a web server: run Castor with PHP, or compile your application with the "--php-cli" option.');
        }

        $phpCli = "{$extractionDirectory}/{$binary}";
        $this->filesystem->copy($embeddedPhpCli, $phpCli);
        $this->filesystem->chmod($phpCli, 0o755);

        return $phpCli;
    }

    private function getDocumentRoot(string $documentRoot, string $extractionDirectory): string
    {
        if (!str_starts_with($documentRoot, 'phar://')) {
            return $documentRoot;
        }

        $extractedDocumentRoot = "{$extractionDirectory}/document-root";
        $this->filesystem->mirror($documentRoot, $extractedDocumentRoot);

        return $extractedDocumentRoot;
    }
}
