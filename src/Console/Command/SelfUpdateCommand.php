<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Castor\Helper\Installation;
use Castor\Helper\InstallationMethod;
use Castor\Http\HttpDownloader;
use JoliCode\PhpOsHelper\OsHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal */
#[AsCommand(
    name: 'self-update',
    description: 'Updates Castor to the latest version',
    aliases: ['self:update'],
)]
class SelfUpdateCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
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

        return $this->updateBinary($io, $input, $installationMethod, $currentPath);
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

    private function updateBinary(SymfonyStyle $io, InputInterface $input, InstallationMethod $installationMethod, string $currentPath): int
    {
        $io->section('Checking for updates...');

        $latestVersion = $this->fetchLatestVersion();

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

        $downloadUrl = $this->getDownloadUrl($latestVersion, $installationMethod);

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

        $tempFile = sys_get_temp_dir() . '/castor-update-' . uniqid();

        try {
            $this->httpDownloader->download($downloadUrl, $tempFile);
        } catch (\Throwable $e) {
            $io->error(\sprintf('Failed to download update: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if (!$input->getOption('no-backup')) {
            $backupPath = $currentPath . '.backup';
            $io->text(\sprintf('Creating backup at: <comment>%s</comment>', $backupPath));
            $this->filesystem->copy($currentPath, $backupPath, true);
        }

        $this->filesystem->chmod($tempFile, 0o755);

        $io->text('Verifying new binary...');
        $verifyProcess = new Process([$tempFile, '--version']);
        $verifyProcess->run();

        if (!$verifyProcess->isSuccessful()) {
            $io->error('The downloaded binary appears to be corrupted. Update aborted.');
            $this->filesystem->remove($tempFile);

            return Command::FAILURE;
        }

        $io->text('Replacing current binary...');
        $this->filesystem->rename($tempFile, $currentPath, true);
        $this->filesystem->chmod($currentPath, 0o755);

        $io->newLine();
        $io->success(\sprintf('Castor has been updated from %s to %s!', $currentVersion, $latestTag));

        if (!$input->getOption('no-backup')) {
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

    /**
     * @return array<string, mixed>|null
     */
    private function fetchLatestVersion(): ?array
    {
        try {
            return $this
                ->httpClient
                ->request('GET', 'https://api.github.com/repos/jolicode/castor/releases/latest', [
                    'timeout' => 10,
                ])
                ->toArray()
            ;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $latestVersion
     */
    private function getDownloadUrl(array $latestVersion, InstallationMethod $installationMethod): ?string
    {
        $assets = $latestVersion['assets'] ?? [];

        $assets = match (true) {
            OsHelper::isWindows() || OsHelper::isWindowsSubsystemForLinux() => array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], 'windows')),
            OsHelper::isMacOS() => array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], 'darwin')),
            OsHelper::isUnix() => array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], 'linux')),
            default => [],
        };

        $architecture = $this->installation->getArchitecture();
        $assets = array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], $architecture->value));

        if (InstallationMethod::Static === $installationMethod) {
            $assets = array_filter($assets, static fn (array $asset): bool => !str_ends_with((string) $asset['name'], '.phar'));
        } else {
            $assets = array_filter($assets, static fn (array $asset): bool => str_ends_with((string) $asset['name'], '.phar'));
        }

        $asset = reset($assets);

        return $asset['browser_download_url'] ?? null;
    }
}
