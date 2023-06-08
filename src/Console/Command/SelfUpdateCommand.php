<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Castor\FunctionFinder;
use Joli\JoliNotif\Util\OsHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class SelfUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setAliases(['selfupdate'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $latestReleaseData = @file_get_contents('https://api.github.com/repos/jolicode/castor/releases/latest');
        $latestVersion = json_decode($latestReleaseData['tag_name'], true);

        if ($latestVersion === Application::VERSION) {
            $output->writeln('Castor is already up to date.');

            return Command::SUCCESS;
        }

        $latestReleaseUrl = match(true) {
            OsHelper::isWindows() => array_filter($latestReleaseData['assets'], fn ($asset) => str_contains('windows', $asset['browser_download_url']))[0]['browser_download_url'],
            OsHelper::isMacOS() => array_filter($latestReleaseData['assets'], fn ($asset) => str_contains('darwin', $asset['browser_download_url']))[0]['browser_download_url'],
            OsHelper::isUnix() => array_filter($latestReleaseData['assets'], fn ($asset) => str_contains('linux', $asset['browser_download_url']))[0]['browser_download_url'],
        };

        return Command::SUCCESS;
    }
}
