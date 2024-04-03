<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Castor\ContextRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
#[AsCommand(
    name: 'castor:debug',
    description: 'Debug the application',
    hidden: true,
    aliases: ['debug'],
)]
final class DebugCommand extends Command
{
    public function __construct(
        private readonly string $rootDir,
        private readonly string $cacheDir,
        private readonly ContextRegistry $contextRegistry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $table = [
            'Application name' => Application::NAME,
            'Application version' => Application::VERSION,
            'Root directory' => $this->rootDir,
            'Cache directory' => $this->cacheDir,
            'Current context name' => $this->contextRegistry->getCurrentContext()->name,
            'Current context data' => json_encode($this->contextRegistry->getCurrentContext()->__debugInfo(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        ];

        $io->horizontalTable(array_keys($table), [array_values($table)]);

        return 0;
    }
}
