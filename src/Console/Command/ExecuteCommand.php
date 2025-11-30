<?php

namespace Castor\Console\Command;

use Castor\Console\Input\GetRawTokenTrait;
use Castor\Console\Output\VerbosityLevel;
use Castor\ContextRegistry;
use Castor\Exception\ProblemException;
use Castor\Import\Remote\Composer;
use Castor\Runner\PhpRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

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
        private readonly Filesystem $filesystem,
        private readonly PhpRunner $phpRunner,
        private readonly ContextRegistry $contextRegistry,
        private readonly string $cacheDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('deps', 'd', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Additional dependencies to install')
            ->addArgument('package', InputArgument::REQUIRED, 'Package to execute in the format "vendor/package:version@binary" or "vendor/package@binary" or "vendor/package"')
            ->ignoreValidationErrors()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create temporary directory
        $tmpDir = tempnam($this->cacheDir, 'castor-exec-');

        if (!$tmpDir) {
            throw new \RuntimeException('Could not create temporary directory');
        }

        unlink($tmpDir);

        // format of execute is "vendor/package:version@binary"
        /** @var string[] $deps */
        $deps = $input->getOption('deps') ?? [];
        $name = $input->getArgument('package');
        $nameSplitted = explode('@', $name);

        if (\count($nameSplitted) >= 2) {
            $deps[] = $nameSplitted[0];
            $binary = $nameSplitted[1];
        } else {
            $deps[] = $name;
            $binary = null;
        }

        $composerJsonPath = $tmpDir . '/composer.json';
        $vendorDirectory = $tmpDir . '/vendor';
        $binaryDirectory = $tmpDir . '/bin';
        $tokens = $this->getRawTokens($input, false);

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
            $this->filesystem->mkdir($tmpDir, 0o755);
            /** @var list<string> $composerArgs */
            $composerArgs = ['require', ...$deps, '--no-interaction'];
            $verbosityLevel = VerbosityLevel::fromSymfonyOutput($output);
            if (!$verbosityLevel->isVerbose()) {
                $composerArgs[] = '--quiet';
            }
            $this->composer->run($composerJsonPath, $vendorDirectory, $composerArgs, $output, $input->isInteractive(), $binaryDirectory);

            if (null === $binary) {
                // / Get first binary declared in the package if none was specified
                $lockFilePath = $tmpDir . '/composer.lock';
                $lockContent = json_decode(file_get_contents($lockFilePath) ?: '{}', true);

                $foundPackage = [];

                foreach ($lockContent['packages'] as $package) {
                    if ($package['name'] === $nameSplitted[0]) {
                        $foundPackage = $package;

                        break;
                    }
                }

                $binary = $foundPackage['bin'][0] ?? null;
                $binary = $binary ? basename($binary) : null;

                if (null === $binary) {
                    throw new ProblemException("No binary found for package '{$nameSplitted[0]}': you must use a package with a vendor binary, see \nhttps://getcomposer.org/doc/articles/vendor-binaries.md for more information");
                }
            }

            $context = $this->contextRegistry->getCurrentContext()->withAllowFailure();

            // By default the run command will use the directory of the root castor.php file
            // but this command should be executed in the same directory as where it was launched
            if (($workingDir = getcwd()) !== false) {
                $context = $context->withWorkingDirectory($workingDir);
            }

            return $this->phpRunner->run($binaryDirectory . '/' . $binary, $args, $context)->wait();
        } finally {
            $this->filesystem->remove($tmpDir);
        }
    }
}
