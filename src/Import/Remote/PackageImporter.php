<?php

namespace Castor\Import\Remote;

use Castor\Helper\PathHelper;
use Castor\Import\Exception\ImportError;
use Castor\Import\Exception\InvalidImportFormat;
use Castor\Import\Exception\RemoteNotAllowed;
use Castor\Import\Mount;
use Castor\Kernel;
use Symfony\Component\Console\Input\InputInterface;

/** @internal */
class PackageImporter
{
    public function __construct(
        private readonly InputInterface $input,
        private readonly Composer $composer,
        private readonly Kernel $kernel,
    ) {
    }

    public function requireAutoload(): void
    {
        $autoloadPath = PathHelper::getRoot() . Composer::VENDOR_DIR . 'autoload.php';

        if (!file_exists($autoloadPath)) {
            return;
        }

        require $autoloadPath;
    }

    public function install(Mount $mount, bool $update = false, bool $displayProgress = true): void
    {
        $this->composer->install($mount->path, $update, $displayProgress);
    }

    public function importFromPackage(string $scheme, string $package, ?string $file = null): void
    {
        if (!$this->allowsRemote()) {
            throw new RemoteNotAllowed('Remote imports are disabled.');
        }

        if (!preg_match('#^(?<organization>[^/]+)/(?<repository>[^/]+)$#', $package)) {
            throw new InvalidImportFormat(sprintf('The import path must be formatted like this: "%s://<organization>/<repository>".', $scheme));
        }

        if ('composer' === $scheme || 'package' === $scheme) {
            if ('package' === $scheme) {
                @trigger_deprecation('castor/castor', '0.16.0', 'The "package" scheme is deprecated, use "composer" instead.');
            }

            $packageDirectory = PathHelper::getRoot() . Composer::VENDOR_DIR . $package;

            if (!file_exists($packageDirectory)) {
                throw new ImportError(sprintf('The package "%s" is not installed, make sure you required it in your composer-castor.json file.', $package));
            }

            $this->kernel->addMount(new Mount(
                PathHelper::getRoot() . Composer::VENDOR_DIR . $package . '/' . ($file ?? ''),
                allowEmptyEntrypoint: true,
                allowRemotePackage: false,
            ));

            return;
        }

        throw new InvalidImportFormat(sprintf('The import scheme "%s" is not supported.', $scheme));
    }

    public function clean(): void
    {
        $this->composer->remove();
    }

    public function allowsRemote(): bool
    {
        if ($_SERVER['CASTOR_NO_REMOTE'] ?? false) {
            return false;
        }

        // Need to look for the raw options as the input is not yet parsed
        return true === $this->input->getParameterOption('--no-remote', true);
    }
}
