<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Castor\Helper\Installation;
use Castor\Helper\InstallationMethod;
use Castor\Helper\ReleaseHelper;
use Castor\Http\HttpDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @internal */
#[AsCommand(
    name: 'self-update',
    description: 'Updates Castor to the latest version',
    aliases: ['self:update'],
)]
class SelfUpdateCommand extends Command
{
    public function __construct(
        private readonly ReleaseHelper $releaseHelper,
        private readonly HttpDownloader $httpDownloader,
        private readonly Installation $installation,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update even if already up to date')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Skip creating a backup of the current binary')
            ->addOption('rollback', 'r', InputOption::VALUE_NONE, 'Rollback to the previous version')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $installationMethod = $this->installation->getMethod();
        $currentPath = $this->installation->getPath();

        if ($input->getOption('rollback')) {
            return $this->rollback($io, $currentPath);
        }

        if (!\in_array($installationMethod, [InstallationMethod::Phar, InstallationMethod::Static, InstallationMethod::ComposerGlobal], true)) {
            return $this->handleUnsupportedInstallationMethod($io, $installationMethod);
        }

        if (InstallationMethod::ComposerGlobal === $installationMethod) {
            return $this->updateViaComposer($io);
        }

        return $this->updateBinary($io, $input, $currentPath);
    }

    private function updateViaComposer(SymfonyStyle $io): int
    {
        $io->section('Updating Castor via Composer...');

        $process = new Process(['composer', 'global', 'update', 'jolicode/castor']);
        $process->setTimeout(300);
        $process->run(static function (string $type, string $buffer) use ($io): void {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Failed to update Castor via Composer.');

            return Command::FAILURE;
        }

        $io->success('Castor has been updated successfully!');

        return Command::SUCCESS;
    }

    private function updateBinary(SymfonyStyle $io, InputInterface $input, string $currentPath): int
    {
        $io->section('Checking for updates...');

        // Always hit GitHub here: the user explicitly asked for an update, a
        // day-old cached release would be misleading
        $latestVersion = $this->releaseHelper->getLatest(useCache: false, timeout: 10);

        if (null === $latestVersion) {
            $io->error('Failed to fetch latest version information from GitHub.');

            return Command::FAILURE;
        }

        $latestTag = $latestVersion['tag_name'];
        $currentVersion = Application::VERSION;

        $io->text(\sprintf('Current version: <info>%s</info>', $currentVersion));
        $io->text(\sprintf('Latest version:  <info>%s</info>', $latestTag));
        $io->newLine();

        if (!$input->getOption('force') && version_compare($latestTag, $currentVersion, '<=')) {
            $io->success('You are already using the latest version of Castor.');

            return Command::SUCCESS;
        }

        $downloadUrl = $this->releaseHelper->getDownloadUrl($latestVersion);

        if (null === $downloadUrl) {
            $io->error('Could not find a suitable download for your platform.');

            return Command::FAILURE;
        }

        if (!is_writable(\dirname($currentPath))) {
            $io->error(\sprintf(
                'Cannot update: directory "%s" is not writable. Try running with elevated privileges.',
                \dirname($currentPath)
            ));

            return Command::FAILURE;
        }

        $io->text(\sprintf('Downloading from: <comment>%s</comment>', $downloadUrl));

        // Download next to the current binary: the final rename() must happen
        // on the same filesystem, and the temp dir is often a different one
        $tempFile = $currentPath . '.tmp';
        $backupPath = $input->getOption('no-backup') ? null : $currentPath . '.backup';

        try {
            $this->httpDownloader->download($downloadUrl, $tempFile);
            $this->filesystem->chmod($tempFile, 0o755);

            $io->text('Verifying new binary...');
            $verifyProcess = new Process([$tempFile, '--version']);
            $verifyProcess->run();

            if (!$verifyProcess->isSuccessful()) {
                $io->error('The downloaded binary appears to be corrupted. Update aborted.');

                return Command::FAILURE;
            }

            if ($backupPath) {
                $io->text(\sprintf('Creating backup at: <comment>%s</comment>', $backupPath));
                $this->filesystem->copy($currentPath, $backupPath, true);
            }

            $io->text('Replacing current binary...');
            $this->filesystem->rename($tempFile, $currentPath, true);
            $this->filesystem->chmod($currentPath, 0o755);
        } catch (IOExceptionInterface|\RuntimeException $e) {
            $io->error(\sprintf('Failed to update Castor: %s', $e->getMessage()));

            return Command::FAILURE;
        } finally {
            $this->filesystem->remove($tempFile);
        }

        $io->newLine();
        $io->success(\sprintf('Castor has been updated from %s to %s!', $currentVersion, $latestTag));

        if ($backupPath) {
            $io->note('A backup of the previous version has been saved. Use --rollback to restore it.');
        }

        return Command::SUCCESS;
    }

    private function handleUnsupportedInstallationMethod(SymfonyStyle $io, InstallationMethod $installationMethod): int
    {
        $io->error(\sprintf(
            'Self-update is not supported for "%s" installation method.',
            $installationMethod->value
        ));

        match ($installationMethod) {
            InstallationMethod::Composer => $io->block(
                'Castor is installed as a project dependency via Composer. ' .
                'Updating it manually would break the consistency with your composer.lock file.',
                'WHY?',
                'fg=yellow',
                ' ',
            ),
            InstallationMethod::Source => $io->block(
                'Castor is running from source (Git checkout). ' .
                'Replacing files would break your Git repository.',
                'WHY?',
                'fg=yellow',
                ' ',
            ),
            default => null,
        };

        $updateCommand = match ($installationMethod) {
            InstallationMethod::Composer => 'composer update jolicode/castor',
            InstallationMethod::Source => 'git pull',
            default => null,
        };

        if ($updateCommand) {
            $io->block(\sprintf('To update, run: <comment>%s</comment>', $updateCommand), 'TIP', 'fg=green', ' ', escape: false);
        }

        return Command::FAILURE;
    }

    private function rollback(SymfonyStyle $io, string $currentPath): int
    {
        $backupPath = $currentPath . '.backup';

        if (!file_exists($backupPath)) {
            $io->error('No backup found. Cannot rollback.');

            return Command::FAILURE;
        }

        $io->section('Rolling back to previous version...');

        $this->filesystem->rename($backupPath, $currentPath, true);
        $this->filesystem->chmod($currentPath, 0o755);

        $io->success('Successfully rolled back to the previous version.');

        return Command::SUCCESS;
    }
}
