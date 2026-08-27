<?php

namespace Castor\Listener;

use Castor\Console\Application;
use Castor\Exception\MinimumVersionRequirementNotMetException;
use Castor\Helper\Installation;
use Castor\Helper\InstallationMethod;
use Castor\Helper\PlatformHelper;
use Castor\Helper\ReleaseHelper;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** @internal */
class UpdateCastorListener
{
    public function __construct(
        private readonly ReleaseHelper $releaseHelper,
        private readonly Installation $installation,
        #[Autowire('%repacked%')]
        private readonly bool $repacked,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    // Must be before the command is executed, because we have to check for many
    // command options
    #[AsEventListener()]
    public function checkUpdate(ConsoleCommandEvent $event): void
    {
        if ($this->repacked) {
            return;
        }

        if (PlatformHelper::getEnv('DISABLE_VERSION_CHECK')) {
            trigger_deprecation('castor/castor', '1.1.0', 'The "DISABLE_VERSION_CHECK" environment var is deprecated, use "CASTOR_DISABLE_VERSION_CHECK" instead.');

            return;
        }

        if (PlatformHelper::getEnv('CASTOR_DISABLE_VERSION_CHECK')) {
            return;
        }

        if (PlatformHelper::isRunningInAgentContext()) {
            return;
        }

        $command = $event->getCommand();
        if (!$command) {
            return;
        }
        if (\in_array($command->getName(), [
            'completion',
            '_complete',
        ])) {
            return;
        }

        $input = $event->getInput();
        if ($input->hasOption('format') && 'json' === $input->getOption('format')) {
            return;
        }

        $this->displayUpdateWarningIfNeeded($input, $event->getOutput());
    }

    // Run this on exception to force the check
    #[AsEventListener()]
    public function forceCheckUpdate(ConsoleErrorEvent $event): void
    {
        $error = $event->getError();

        if ($error instanceof MinimumVersionRequirementNotMetException) {
            $this->displayUpdateWarningIfNeeded($event->getInput(), $event->getOutput(), false);
        }
    }

    private function displayUpdateWarningIfNeeded(InputInterface $input, OutputInterface $output, bool $useCache = true): void
    {
        if (Application::isSnapshot()) {
            $this->displaySnapshotUpdateWarningIfNeeded($input, $output, $useCache);

            return;
        }

        $latestVersion = $this->releaseHelper->getLatest($useCache);

        if (!$latestVersion) {
            return;
        }

        if (version_compare($latestVersion['tag_name'], Application::VERSION, '<=')) {
            return;
        }

        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->block(\sprintf('<info>A new Castor version is available</info> (<comment>%s</comment>, currently running <comment>%s</comment>).', $latestVersion['tag_name'], Application::VERSION), escape: false);

        $installationMethod = $this->installation->getMethod();

        if (\in_array($installationMethod, [InstallationMethod::Phar, InstallationMethod::Static], true)) {
            if (OsHelper::isUnix()) {
                $symfonyStyle->block('Run the following command to update Castor:');
                $symfonyStyle->block('<comment>castor self-update</comment>', escape: false);
            } else {
                $latestReleaseUrl = $this->releaseHelper->getDownloadUrl($latestVersion);

                if (!$latestReleaseUrl) {
                    $this->logger->info('Failed to detect the correct release URL adapted to your system.');

                    return;
                }

                $symfonyStyle->block(\sprintf('Download the latest version at <comment>%s</comment>', $latestReleaseUrl), escape: false);
            }

            $symfonyStyle->newLine();

            return;
        }

        if (InstallationMethod::ComposerGlobal === $installationMethod) {
            $symfonyStyle->block('Run the following command to update Castor:');
            $symfonyStyle->block('<comment>castor self-update</comment>', escape: false);
        }
    }

    private function displaySnapshotUpdateWarningIfNeeded(InputInterface $input, OutputInterface $output, bool $useCache): void
    {
        $snapshot = $this->releaseHelper->getRelease(ReleaseHelper::SNAPSHOT_TAG, $useCache);

        // The snapshot pre-release is named after the version it was built from
        if (!$snapshot || ($snapshot['name'] ?? null) === Application::VERSION) {
            return;
        }

        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->block(\sprintf('<info>A new Castor snapshot is available</info> (<comment>%s</comment>, currently running <comment>%s</comment>).', $snapshot['name'], Application::VERSION), escape: false);
        $symfonyStyle->block('Run the following command to update Castor:');
        $symfonyStyle->block('<comment>castor self-update --snapshot</comment>', escape: false);
        $symfonyStyle->newLine();
    }
}
