<?php

namespace Castor\Console\Command;

use Castor\Helper\PathHelper;
use Castor\Import\Importer;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** @internal */
class RepackCommand extends Command
{
    public function __construct(
        #[Autowire(lazy: true)]
        private readonly Importer $importer,
        #[Autowire(lazy: true)]
        private readonly Composer $composer,
        #[Autowire(lazy: true)]
        private readonly Filesystem $fs,
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
            ->addOption('no-logo', null, InputOption::VALUE_NONE, 'Hide Castor logo')
            ->addOption('logo-file', null, InputOption::VALUE_OPTIONAL, 'Path to a PHP file that returns a logo as a string, or a closure that returns a logo as a string')
            ->addOption('output-directory', null, InputOption::VALUE_REQUIRED, 'Path to the directory where the phar will be generated', '')
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (str_starts_with(__FILE__, 'phar:')) {
            throw new \RuntimeException('This command cannot be run from a phar. You must install castor with its sources.');
        }

        $os = $input->getOption('os');
        if (!\in_array($os, ['linux', 'darwin', 'windows'])) {
            throw new \InvalidArgumentException('The os option must be one of linux, darwin or windows.');
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

        $castorSourceDir = PathHelper::realpath(__DIR__ . '/../../..');

        $boxConfigFile = "{$castorSourceDir}/tools/phar/box.{$os}-amd64.json";
        if (!file_exists($boxConfigFile)) {
            throw new \RuntimeException('Could not find the phar configuration.');
        }

        $appName = $input->getOption('app-name');
        $appVersion = $input->getOption('app-version');
        $hideLogo = $input->getOption('no-logo') ? 'true' : 'false';
        $externalLogo = $this->getExternalLogo($input->getOption('logo-file'), $appName, $appVersion);

        $alias = 'alias.phar';
        $main = <<<PHP
            <?php

            require __DIR__ . '/vendor/autoload.php';

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

        $boxConfig = json_decode((string) file_get_contents($boxConfigFile), true, 512, \JSON_THROW_ON_ERROR);
        $boxConfig['base-path'] = '.';
        $boxConfig['main'] = '.main.php';
        $boxConfig['alias'] = $alias;
        $boxConfig['output'] = \sprintf('%s%s.%s.phar', $outputDirectory, $appName, $os);
        // update all paths to point to the castor source
        foreach (['files', 'files-bin', 'directories', 'directories-bin'] as $key) {
            if (!\array_key_exists($key, $boxConfig)) {
                continue;
            }
            $boxConfig[$key] = [
                ...array_map(
                    static fn (string $file): string => $castorSourceDir . '/' . $file,
                    $boxConfig[$key] ?? []
                ),
            ];
        }
        // add all files from the FunctionFinder, this is useful if the file
        // are in a hidden directory, because it's not included by default by
        // box
        $boxConfig['files'] = [
            ...array_map(
                static fn (string $file): string => str_replace(PathHelper::getRoot() . '/', '', $file),
                $this->importer->getImports(),
            ),
            ...$boxConfig['files'] ?? [],
        ];

        // Add .castor directory
        if (file_exists('.castor')) {
            $boxConfig['directories'] = [
                '.castor',
                ...$boxConfig['directories'] ?? [],
            ];
        }

        // Force discovery
        $boxConfig['force-autodiscovery'] = true;

        if (file_exists($appBoxConfigFile = PathHelper::getRoot() . '/box.json')) {
            $appBoxConfig = json_decode((string) file_get_contents($appBoxConfigFile), true, 512, \JSON_THROW_ON_ERROR);

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

        file_put_contents('.box.json', json_encode($boxConfig, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        file_put_contents('.main.php', $main);

        $process = new Process([$box, 'compile', '--config=.box.json']);

        try {
            $process->mustRun(static fn ($type, $buffer) => print $buffer);
        } finally {
            unlink('.box.json');
            unlink('.main.php');
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
}
