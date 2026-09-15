<?php

namespace Castor\Import\Remote;

use Composer\Command\SelfUpdateCommand;
use Composer\Composer as ComposerInstance;
use Composer\Console\Application as BaseApplication;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
final class ComposerApplication extends BaseApplication
{
    private ?ComposerInstance $declaredOn = null;

    public function __construct(
        private readonly BundledPackages $bundledPackages,
        private readonly bool $useBundledPackages = true,
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
        // castor.composer.json (like "require" does), so the packages bundled
        // with Castor are declared on every instance
        if ($composer && $this->useBundledPackages && $composer !== $this->declaredOn) {
            // In front of the other repositories, so its versions are the only
            // ones known for these packages
            $composer->getRepositoryManager()->prependRepository($this->bundledPackages->createRepository());
            $this->declaredOn = $composer;
        }

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
