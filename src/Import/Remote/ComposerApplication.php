<?php

namespace Castor\Import\Remote;

use Composer\Command\SelfUpdateCommand;
use Composer\Composer as ComposerInstance;
use Composer\Console\Application as BaseApplication;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
final class ComposerApplication extends BaseApplication
{
    private ?ComposerInstance $configured = null;

    /**
     * @param string|null $castorPluginLockFile The lock file of the remote packages, when Composer runs on the
     *                                          composer.json of a Castor plugin, in its own repository
     */
    public function __construct(
        private readonly BundledPackages $bundledPackages,
        private readonly bool $useBundledPackages = true,
        private readonly ?string $castorPluginLockFile = null,
    ) {
        parent::__construct();
    }

    /**
     * @phpstan-return ($required is true ? ComposerInstance : ComposerInstance|null)
     */
    public function getComposer(bool $required = true, ?bool $disablePlugins = null, ?bool $disableScripts = null): ?ComposerInstance
    {
        $composer = parent::getComposer($required, $disablePlugins, $disableScripts);

        // Composer builds a new instance once a command modified
        // castor.composer.json (like "require" does), so it is configured
        // again on every instance
        if (!$composer || $composer === $this->configured) {
            return $composer;
        }

        if ($this->useBundledPackages) {
            // In front of the other repositories, so its versions are the only
            // ones known for these packages
            $composer->getRepositoryManager()->prependRepository($this->bundledPackages->createRepository());
        }

        if (null !== $this->castorPluginLockFile) {
            // The composer.lock of the plugin locks the packages of its vendor/
            // directory, with its dev requirements: the tools of its
            // development, which do not run in Castor
            $package = $composer->getPackage();
            $package->setDevRequires([]);
            $package->setDevAutoload([]);

            $io = $this->getIO();
            $composer->setLocker(new Locker($io, new JsonFile($this->castorPluginLockFile, null, $io), $composer->getInstallationManager(), (string) file_get_contents(Factory::getComposerFile()), $composer->getLoop()->getProcessExecutor()));
        }

        $this->configured = $composer;

        return $composer;
    }

    protected function getDefaultCommands(): array
    {
        $commands = [];

        foreach (parent::getDefaultCommands() as $command) {
            if ($command instanceof SelfUpdateCommand) {
                continue;
            }

            $commands[] = $command;
        }

        return $commands;
    }
}
