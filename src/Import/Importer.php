<?php

namespace Castor\Import;

use Castor\Import\Exception\ImportError;
use Castor\Import\Exception\RemoteNotAllowed;
use Castor\Import\Remote\PackageImporter;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

use function Castor\Internal\castor_require;
use function Castor\Internal\fix_exception;

/** @internal */
class Importer
{
    public function __construct(
        private readonly PackageImporter $packageImporter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @phpstan-param ImportSource $source */
    public function import(string $path, ?string $file = null, ?string $version = null, ?string $vcs = null, ?array $source = null): void
    {
        $scheme = parse_url($path, \PHP_URL_SCHEME);

        // Windows paths are not URLs even if parse_url() returns a scheme for the drive letter
        if ($scheme && OsHelper::isWindows() && preg_match('@^\w+:\\\@', $path)) {
            $scheme = null;
        }

        if ($scheme) {
            $package = mb_substr($path, mb_strlen($scheme) + 3);

            try {
                $this->packageImporter->importPackage(
                    $scheme,
                    $package,
                    $file,
                    $version,
                    $vcs,
                    $source,
                );

                return;
            } catch (ImportError $e) {
                throw $this->createImportException($package, $e->getMessage(), $e);
            } catch (RemoteNotAllowed $e) {
                $this->logger->warning($this->getImportLocatedMessage($path, $e->getMessage(), 1));

                return;
            }
        } elseif (null !== $file || null !== $version || null !== $vcs || null !== $source) {
            throw $this->createImportException($path, 'The "file", "version", "vcs" and "source" arguments can only be used with a remote import.');
        }

        if (!file_exists($path)) {
            throw $this->createImportException($path, sprintf('The file "%s" does not exist.', $path));
        }

        if (is_file($path)) {
            castor_require($path);
        }

        if (is_dir($path)) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->notPath('/vendor\/composer/')
                ->notName('autoload.php')
                ->in($path)
            ;

            foreach ($files as $file) {
                castor_require($file->getPathname());
            }
        }
    }

    public function require(string $path): void
    {
        if (file_exists($file = $path . '/castor.php')) {
            castor_require($file);
        } elseif (file_exists($file = $path . '/.castor/castor.php')) {
            castor_require($file);
        } else {
            throw new \RuntimeException('Could not find root "castor.php" file.');
        }

        $castorDirectory = $path . '/castor';
        if (is_dir($castorDirectory)) {
            trigger_deprecation('castor', '0.15', 'Autoloading functions from the "/castor/" directory is deprecated. Import files by yourself with the "castor\import()" function.');
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->in($castorDirectory)
            ;

            foreach ($files as $file) {
                castor_require($file->getPathname());
            }
        }
    }

    private function getImportLocatedMessage(string $path, string $reason, int $depth): string
    {
        /** @var array{file: string, line: int} $caller */
        $caller = debug_backtrace()[$depth + 1];

        return sprintf(
            'Could not import "%s" in "%s" on line %d. Reason: %s',
            $path,
            $caller['file'],
            $caller['line'],
            $reason,
        );
    }

    private function createImportException(string $path, string $message, ?\Throwable $e = null): \Throwable
    {
        $depth = 2;

        return fix_exception(
            new \InvalidArgumentException($this->getImportLocatedMessage($path, $message, $depth), previous: $e),
            $depth
        );
    }
}
