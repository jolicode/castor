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
    /**
     * @var array<string, true>
     */
    private array $imports = [];

    /**
     * @var list<\Fiber>
     */
    private array $importedFilesExecution = [];

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
                $this->packageImporter->addPackage(
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
            $this->importFile($path);
        }

        if (is_dir($path)) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->notPath('vendor')
                ->in($path)
            ;

            foreach ($files as $file) {
                $this->importFile($file->getPathname());
            }
        }
    }

    public function importFile(string $file): void
    {
        if (isset($this->imports[$file])) {
            return;
        }

        $this->imports[$file] = true;

        $fiber = new \Fiber(function () use ($file) {
            castor_require($file);
        });
        $fiber->start();

        $this->importedFilesExecution[] = $fiber;
    }

    /**
     * @return list<string>
     */
    public function getImports(): array
    {
        return array_keys($this->imports);
    }

    /**
     * @return list<\Fiber>
     */
    public function getImportedFilesExecution(): array
    {
        return $this->importedFilesExecution;
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
