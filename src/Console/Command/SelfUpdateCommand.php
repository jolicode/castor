<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Castor\Helper\Installation;
use Castor\Helper\InstallationMethod;
use Castor\Helper\ReleaseHelper;
use Castor\Http\HttpDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @internal */
#[AsCommand(
    name: 'castor:self-update',
    description: 'Updates Castor to the latest version',
    aliases: ['self-update'],
)]
final readonly class SelfUpdateCommand
{
    public function __construct(
        private ReleaseHelper $releaseHelper,
        private HttpDownloader $httpDownloader,
        private Installation $installation,
        private Filesystem $filesystem,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Force update even if already up to date', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Skip creating a backup of the current binary')]
        bool $noBackup = false,
        #[Option(description: 'Rollback to the previous version', shortcut: 'r')]
        bool $rollback = false,
    ): int {
        $installationMethod = $this->installation->getMethod();
        $currentPath = $this->installation->getPath();

        if ($rollback) {
            return $this->rollback($io, $currentPath);
        }

        if (!$installationMethod->isSelfUpdateable()) {
            return $this->handleUnsupportedInstallationMethod($io, $installationMethod);
        }

        if (InstallationMethod::ComposerGlobal === $installationMethod) {
            return $this->updateViaComposer($io);
        }

        return $this->updateBinary($io, $force, $noBackup, $currentPath);
    }

    private function updateViaComposer(SymfonyStyle $io): int
    {
        $io->section('Updating Castor via Composer...');

        $process = new Process(['composer', 'global', 'update', 'jolicode/castor']);
        $process->setTimeout(300);
        $process->mustRun(static function (string $type, string $buffer) use ($io): void {
            $io->write($buffer);
        });

        $io->success('Castor has been updated successfully!');

        return Command::SUCCESS;
    }

    private function updateBinary(SymfonyStyle $io, bool $force, bool $noBackup, string $currentPath): int
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

        if (!$force && version_compare($latestTag, $currentVersion, '<=')) {
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
        $backupPath = $noBackup ? null : $currentPath . '.backup';

        try {
            $this->httpDownloader->download($downloadUrl, $tempFile);
            $this->filesystem->chmod($tempFile, 0o755);

            $io->text('Verifying new binary...');
            $verifyProcess = new Process([$tempFile, '--version']);
            $verifyProcess->run();

            if (!$verifyProcess->isSuccessful()) {
                $io->error('The downloaded binary appears to be corrupted. Update aborted.');
                $this->filesystem->remove($tempFile);

                return Command::FAILURE;
            }

            if ($backupPath) {
                $io->text(\sprintf('Creating backup at: <comment>%s</comment>', $backupPath));
                $this->filesystem->copy($currentPath, $backupPath, true);

                $io->note('A backup of the previous version has been saved. Use --rollback to restore it.');
            }
        } catch (IOExceptionInterface|\RuntimeException $e) {
            $io->error(\sprintf('Failed to update Castor: %s', $e->getMessage()));
            $this->filesystem->remove($tempFile);

            return Command::FAILURE;
        }

        $io->text('Replacing current binary...');

        $this->replaceRunningBinary($tempFile, $currentPath, \sprintf('Castor has been updated from %s to %s!', $currentVersion, $latestTag));
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

        $this->filesystem->chmod($backupPath, 0o755);

        $this->replaceRunningBinary($backupPath, $currentPath, 'Successfully rolled back to the previous version.');
    }

    /**
     * Replaces the file of the currently running binary, then exits
     * immediately.
     *
     * The running phar or static binary lazily re-reads its own file, by path,
     * every time a new class is loaded: executing any further code once the
     * file has been replaced crashes with a phar corruption error. So this
     * method must be the very last thing the command does, and the success
     * message is written with fwrite() because nothing may be lazy-loaded
     * anymore.
     */
    private function replaceRunningBinary(string $newBinary, string $currentPath, string $successMessage): never
    {
        try {
            $this->filesystem->rename($newBinary, $currentPath, true);
        } catch (IOExceptionInterface $e) {
            // Keep $newBinary in place: when rolling back, it is the only
            // remaining copy of the previous version
            fwrite(\STDERR, \sprintf('Failed to replace the binary: %s', $e->getMessage()) . \PHP_EOL);

            exit(Command::FAILURE);
        }

        fwrite(\STDOUT, \PHP_EOL . ' [OK] ' . $successMessage . \PHP_EOL);

        exit(Command::SUCCESS);
    }
}
