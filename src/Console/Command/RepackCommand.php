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
            }

            ApplicationFactory::create()->run();
            PHP;

        $boxConfig = json_decode((string) file_get_contents($boxConfigFile), true, 512, \JSON_THROW_ON_ERROR);
        $boxConfig['base-path'] = '.';
        $boxConfig['main'] = '.main.php';
        $boxConfig['alias'] = $alias;
        $boxConfig['output'] = \sprintf('%s.%s.phar', $appName, $os);
        // update all paths to point to the castor source
        foreach (['files', 'files-bin', 'directories', 'directories-bin'] as $key) {
            if (!\array_key_exists($key, $boxConfig)) {
                continue;
            }
            $boxConfig[$key] = [
                ...array_map(
                    fn (string $file): string => $castorSourceDir . '/' . $file,
                    $boxConfig[$key] ?? []
                ),
            ];
        }
        // add all files from the FunctionFinder, this is useful if the file
        // are in a hidden directory, because it's not included by default by
        // box
        $boxConfig['files'] = [
            ...array_map(
                fn (string $file): string => str_replace(PathHelper::getRoot() . '/', '', $file),
                $this->importer->getImports(),
            ),
            ...$boxConfig['files'] ?? [],
        ];

        // Add .castor directory
        $boxConfig['directories'] = [
            '.castor',
            ...$boxConfig['directories'] ?? [],
        ];

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
            $process->mustRun(fn ($type, $buffer) => print $buffer);
        } finally {
            unlink('.box.json');
            unlink('.main.php');
        }

        return Command::SUCCESS;
    }
}
