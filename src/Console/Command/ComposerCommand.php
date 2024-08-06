<?php

namespace Castor\Console\Command;

use Castor\Console\Input\GetRawTokenTrait;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** @internal */
#[AsCommand(
    name: 'castor:composer',
    description: 'Interact with built-in Composer for castor',
    aliases: ['composer'],
)]
final class ComposerCommand extends Command
{
    use GetRawTokenTrait;

    public function __construct(
        private readonly string $rootDir,
        #[Autowire(lazy: true)]
        private readonly Composer $composer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->ignoreValidationErrors()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!file_exists($file = $this->rootDir . '/castor.composer.json') && !file_exists($file = $this->rootDir . '/.castor/castor.composer.json')) {
            // Default to the root directory (so someone can do a composer init by example)
            $file = $this->rootDir . '/castor.composer.json';
        }

        $vendorDirectory = $this->rootDir . '/' . Composer::VENDOR_DIR;
        $tokens = $this->getRawTokens($input);

        if (\in_array('init', $tokens)) {
            $lastDirectory = basename($this->rootDir);

            if (!\in_array('--name', $tokens)) {
                $tokens[] = '--name';
                $tokens[] = $lastDirectory . '/castor';
            }

            if (!\in_array('--description', $tokens)) {
                $tokens[] = '--description';
                $tokens[] = "Castor commands for project {$lastDirectory}";
            }

            if (!\in_array('--type', $tokens)) {
                $tokens[] = '--type';
                $tokens[] = 'castor-project';
            }
        }

        $this->composer->run($file, $vendorDirectory, $tokens, $output, $input->isInteractive());

        return Command::SUCCESS;
    }
}
