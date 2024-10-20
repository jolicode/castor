<?php

namespace Castor\Console\Command;

use Castor\Console\Input\GetRawTokenTrait;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

use function Castor\run_phar;

/** @internal */
#[AsCommand(
    name: 'castor:execute',
    description: 'Execute a remote task from a packagist directory',
    aliases: ['execute'],
)]
final class ExecuteCommand extends Command
{
    use GetRawTokenTrait;

    public function __construct(
        #[Autowire(lazy: true)]
        private readonly Composer $composer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::REQUIRED, 'Package to execute')
            ->ignoreValidationErrors()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create temporary directory
        $tmpDir = tempnam(sys_get_temp_dir(), 'castor-exec-');

        if (!$tmpDir) {
            throw new \RuntimeException('Could not create temporary directory');
        }

        unlink($tmpDir);

        // format of execute is
        // vendor/package:version@binary
        $name = $input->getArgument('package');
        $nameSplitted = explode('@', $name);

        if (\count($nameSplitted) >= 2) {
            $package = $nameSplitted[0];
            $binary = $nameSplitted[1];
        } else {
            $package = $name;
            $binary = null;
        }

        $fs = new Filesystem();
        $composerJsonPath = $tmpDir . '/composer.json';
        $vendorDirectory = $tmpDir . '/vendor';
        $tokens = $this->getRawTokens($input);

        $args = [];
        $foundPackageName = false;

        foreach ($tokens as $token) {
            if ($foundPackageName) {
                $args[] = $token;
            } else {
                if ($token === $name) {
                    $foundPackageName = true;
                }
            }
        }

        try {
            $fs->mkdir($tmpDir, 0o755);
            $this->composer->run($composerJsonPath, $vendorDirectory, ['require', $package, '--no-interaction'], $output, $input->isInteractive());
            run_phar($vendorDirectory . '/bin/' . $binary, $args);
        } finally {
            $fs->remove($tmpDir);
        }

        return Command::SUCCESS;
    }
}
