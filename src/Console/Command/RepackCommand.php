<?php

namespace Castor\Console\Command;

use Castor\Helper\PathHelper;
use Castor\Import\Importer;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal */
class RepackCommand extends Command
{
    public function __construct(
        #[Autowire(lazy: true)]
        private readonly Importer $importer,
        #[Autowire(lazy: true)]
        private readonly Composer $composer,
        #[Autowire(lazy: true)]
        private readonly HttpClientInterface $httpClient,
        #[Autowire(lazy: true)]
        private readonly Filesystem $fs,
        #[Autowire(lazy: true)]
        private readonly SymfonyStyle $io,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('castor:repack')
            ->setAliases(['repack'])
            ->addOption('app-name', null, InputOption::VALUE_REQUIRED, 'The name of the phar application', 'my-app')
            ->addOption('app-version', null, InputOption::VALUE_REQUIRED, 'The version of the phar application', '1.0.0')
            ->addOption('os', null, InputOption::VALUE_REQUIRED, 'The targeted OS', 'linux', ['linux', 'darwin', 'windows'])
            ->addOption('arch', null, InputOption::VALUE_REQUIRED, 'The targeted CPU architecture', 'amd64', ['amd64', 'arm64'])
            ->addOption('no-logo', null, InputOption::VALUE_NONE, 'Hide Castor logo')
            ->addOption('logo-file', null, InputOption::VALUE_OPTIONAL, 'Path to a PHP file that returns a logo as a string, or a closure that returns a logo as a string')
            ->addOption('output-directory', null, InputOption::VALUE_REQUIRED, 'Path to the directory where the phar will be generated', '')
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $os = $input->getOption('os');
        if (!\in_array($os, ['linux', 'darwin', 'windows'])) {
            throw new \InvalidArgumentException('The os option must be one of linux, darwin or windows.');
        }

        $arch = $input->getOption('arch');
        if (!\in_array($arch, ['amd64', 'arm64'])) {
            throw new \InvalidArgumentException('The arch option must be one of amd64 or arm64.');
        }

        // Windows only supports amd64
        if ('windows' === $os && 'arm64' === $arch) {
            throw new \InvalidArgumentException('Windows only supports amd64 architecture.');
        }

        $finder = new ExecutableFinder();
        $box = $finder->find('box');
        if (!$box) {
            throw new \RuntimeException('Could not find box. Please install it: https://github.com/box-project/box/blob/main/doc/installation.md#installation.');
        }

        $outputDirectory = (string) $input->getOption('output-directory');
        if ($outputDirectory) {
            if (Path::isRelative($outputDirectory)) {
                $outputDirectory = PathHelper::getRoot() . '/' . $outputDirectory;
            }

            $outputDirectory = rtrim($outputDirectory, '/\\') . '/';

            $this->fs->mkdir($outputDirectory);
        }

        // Install the dependencies
        if ($this->composer->isRemoteAllowed()) {
            $this->composer->install(PathHelper::getRoot());
        }

        // Download and extract castor phar from GitHub
        $castorSourceDir = $this->downloadAndExtractCastorPhar($os, $arch);

        $appName = $input->getOption('app-name');
        $appVersion = $input->getOption('app-version');
        $hideLogo = $input->getOption('no-logo') ? 'true' : 'false';
        $externalLogo = $this->getExternalLogo($input->getOption('logo-file'), $appName, $appVersion);

        $alias = 'alias.phar';
        $main = <<<PHP
            <?php

            require __DIR__ . '/.castor-vendor/vendor/autoload.php';

            use Castor\\Console\\ApplicationFactory;
            use Castor\\Console\\Application;

            class RepackedApplication extends Application
            {
                const NAME = '{$appName}';
                const VERSION = '{$appVersion}';
                const ROOT_DIR = 'phar://{$alias}';
                const HIDE_LOGO = {$hideLogo};
                const EXTERNAL_LOGO = '{$externalLogo}';
            }

            ApplicationFactory::create()->run();
            PHP;

        // Build box config dynamically based on extracted phar content
        $boxConfig = [
            'main' => '.main.php',
            'alias' => $alias,
            'output' => \sprintf('%s%s.%s-%s.phar', $outputDirectory, $appName, $os, $arch),
            'compression' => 'GZ',
            'compactors' => [
                'KevinGH\Box\Compactor\Php',
            ],
            'annotations' => false,
            'check-requirements' => false,
            'directories' => [
                $castorSourceDir,
            ],
            'files' => [],
            'force-autodiscovery' => true,
        ];

        // Add all files from the FunctionFinder, this is useful if the file are
        // in a hidden directory, because it's not included by default by box
        foreach ($this->importer->getImports() as $import) {
            $boxConfig['files'][] = str_replace(PathHelper::getRoot() . '/', '', $import);
        }

        // Add .castor directory
        if (file_exists('.castor')) {
            $boxConfig['directories'][] = '.castor';
        }

        if (file_exists($appBoxConfigFile = PathHelper::getRoot() . '/box.json')) {
            $appBoxConfig = json_decode($this->fs->readFile($appBoxConfigFile), true, 512, \JSON_THROW_ON_ERROR);

            if (
                \array_key_exists('base-path', $appBoxConfig)
                || \array_key_exists('main', $appBoxConfig)
                || \array_key_exists('alias', $appBoxConfig)
                || \array_key_exists('output', $appBoxConfig)
            ) {
                throw new \RuntimeException('Application box config could not contains one of this keys: base-path, main, alias or output.');
            }

            $boxConfig = array_merge_recursive(
                $boxConfig,
                $appBoxConfig,
            );
        }

        $this->fs->dumpFile('.box.json', json_encode($boxConfig, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
        $this->fs->dumpFile('.main.php', $main);

        $this->io->comment('Building phar with box...');

        $process = new Process([$box, 'compile', '--config=.box.json']);

        try {
            $process->mustRun(static fn ($type, $buffer) => print $buffer);
        } finally {
            $this->fs->remove([
                '.box.json',
                '.main.php',
                '.castor-vendor',
            ]);
        }

        return Command::SUCCESS;
    }

    protected function getExternalLogo(?string $logoFile, string $appName, string $appVersion): string
    {
        if (null === $logoFile) {
            return '';
        }

        $logoFilePath = $logoFile;
        if (!file_exists($logoFilePath)) {
            $logoFilePath = PathHelper::getRoot() . '/' . ltrim($logoFile, '/');
            if (!file_exists($logoFilePath)) {
                throw new \RuntimeException(\sprintf('The logo file %s does not exist.', $logoFile));
            }
        }

        $externalLogo = require $logoFilePath;

        return match (true) {
            \is_string($externalLogo) => $externalLogo,
            \is_callable($externalLogo) => $externalLogo($appName, $appVersion),
            default => throw new \RuntimeException(\sprintf('The logo file %s returns an unsupported format. Had to be a string or closure.', $logoFile)),
        };
    }

    private function downloadAndExtractCastorPhar(string $os, string $arch): string
    {
        $extractDir = PathHelper::getRoot() . '/.castor-vendor';

        // If already extracted, return the path
        if (is_dir("{$extractDir}/vendor")) {
            $this->io->comment('Using cached Castor sources from .castor-vendor');

            return $extractDir;
        }

        $this->fs->remove($extractDir);
        $this->fs->mkdir($extractDir);

        $this->io->comment('Fetching latest Castor release from GitHub...');

        $options = [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
            ],
        ];
        if ($_SERVER['GITHUB_TOKEN'] ?? false) {
            $options['headers']['Authorization'] = 'Bearer ' . $_SERVER['GITHUB_TOKEN'];
        }

        // Get latest release info from GitHub API
        $releaseInfo = $this->httpClient
            ->request('GET', 'https://api.github.com/repos/jolicode/castor/releases/latest', $options)
            ->toArray()
        ;

        // Find the phar asset for the specified OS and architecture
        $pharName = "castor.{$os}-{$arch}.phar";
        $pharUrl = null;
        foreach ($releaseInfo['assets'] as $asset) {
            if ($pharName === $asset['name']) {
                $pharUrl = $asset['browser_download_url'];

                break;
            }
        }

        if (null === $pharUrl) {
            throw new \RuntimeException(\sprintf('Could not find %s in the latest GitHub release.', $pharName));
        }

        $this->io->comment(\sprintf('Downloading Castor %s (%s-%s)...', $releaseInfo['tag_name'], $os, $arch));

        // Download the phar
        $pharPath = $extractDir . '/' . $pharName;
        $pharContent = $this->httpClient
            ->request('GET', $pharUrl, $options)
            ->getContent()
        ;

        $this->fs->dumpFile($pharPath, $pharContent);

        $this->io->comment('Extracting Castor phar...');

        // Extract the phar
        $phar = new \Phar($pharPath);
        $phar->extractTo($extractDir);

        $this->fs->remove($pharPath);

        $this->io->comment('Castor sources extracted to .castor-vendor');

        return $extractDir;
    }
}
