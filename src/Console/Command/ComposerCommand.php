<?php

namespace Castor\Console\Command;

use Castor\Console\Input\GetRawTokenTrait;
use Castor\Container;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
#[AsCommand(
    name: 'castor:composer',
    description: 'Interact with built-in Composer for castor',
    aliases: ['composer'],
)]
class ComposerCommand extends Command
{
    use GetRawTokenTrait;

    public function __construct(
        private readonly string $rootDir,
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
        $extra = array_filter($this->getRawTokens($input), fn ($item) => 'composer' !== $item);
        $composer = Container::get()->composer;

        $vendorDirectory = $this->rootDir . Composer::VENDOR_DIR;

        if (!file_exists($file = $this->rootDir . '/composer.castor.json') && !file_exists($file = $this->rootDir . '/.castor/composer.castor.json')) {
            // Default to the root directory (so someone can do a composer init
            $file = $this->rootDir . '/composer.castor.json';
        }

        $composer->run($file, $vendorDirectory, $extra, $output);

        return Command::SUCCESS;
    }
}
