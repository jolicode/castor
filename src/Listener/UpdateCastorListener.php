<?php

namespace Castor\Listener;

use Castor\Console\Application;
use Castor\Helper\PlatformHelper;
use JoliCode\PhpOsHelper\OsHelper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpdateCastorListener
{
    public function __construct(
        private readonly CacheItemPoolInterface&CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
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

    private function displayUpdateWarningIfNeeded(InputInterface $input, OutputInterface $output): void
    {
        $item = $this->cache->getItem('castor-releases');

        if ($item->isHit()) {
            $latestVersion = $item->get();
        } else {
            $latestVersion = null;
            $item->expiresAfter(60 * 60 * 24);

            try {
                $latestVersion = $this
                    ->httpClient
                    ->request('GET', 'https://api.github.com/repos/jolicode/castor/releases/latest', [
                        'timeout' => 1,
                    ])
                    ->toArray()
                ;
            } catch (ExceptionInterface) {
                $this->logger->info('Failed to fetch latest Castor version from GitHub.');

                $item->expiresAfter(60 * 10);
            }

            $this->cache->save($item->set($latestVersion));
        }

        if (!$latestVersion) {
            return;
        }

        if (version_compare($latestVersion['tag_name'], Application::VERSION, '<=')) {
            return;
        }

        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->block(\sprintf('<info>A new Castor version is available</info> (<comment>%s</comment>, currently running <comment>%s</comment>).', $latestVersion['tag_name'], Application::VERSION), escape: false);

        // Installed via phar
        if ($pharPath = \Phar::running(false)) {
            $assets = match (true) {
                OsHelper::isWindows() || OsHelper::isWindowsSubsystemForLinux() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'windows')),
                OsHelper::isMacOS() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'darwin')),
                OsHelper::isUnix() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'linux')),
                default => [],
            };

            if (!$assets) {
                $this->logger->info('Failed to detect the correct release url adapted to your system.');

                return;
            }

            $latestReleaseUrl = reset($assets)['browser_download_url'] ?? null;
            // Fow now, we force the phar since it has more capabilities than
            // the static binary, and it's more tested
            if (!str_ends_with($latestReleaseUrl, '.phar')) {
                $latestReleaseUrl .= '.phar';
            }

            if (!$latestReleaseUrl) {
                $this->logger->info('Failed to fetch latest phar url.');

                return;
            }

            if (OsHelper::isUnix()) {
                $symfonyStyle->block('Run the following command to update Castor:');
                $symfonyStyle->block(\sprintf('<comment>curl "https://castor.jolicode.com/install" | bash -s -- --install-dir %s</comment>', \dirname($pharPath)), escape: false);
            } else {
                $symfonyStyle->block(\sprintf('Download the latest version at <comment>%s</comment>', $latestReleaseUrl), escape: false);
            }

            $symfonyStyle->newLine();

            return;
        }

        $globalComposerPath = $this->cache->get('castor-composer-global-path', function (): string {
            $process = new Process(['composer', 'global', 'config', 'home', '--quiet']);
            $process->run();

            return trim($process->getOutput());
        });

        // Installed via composer global
        if ($globalComposerPath && str_contains(__FILE__, $globalComposerPath)) {
            $symfonyStyle->block('Run the following command to update Castor: <comment>composer global update jolicode/castor</comment>', escape: false);
        }
    }
}
