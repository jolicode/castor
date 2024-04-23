<?php

namespace Castor\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('art', description: 'Display beautfil arts about castor', hidden: true)]
class ArtCommand extends Command
{
    protected function configure()
    {
        $this
            ->addOption('emojis', 'e', InputOption::VALUE_REQUIRED, 'Displayed emojis', '🦫💧🔨')
            ->addOption('sleep', 's', InputOption::VALUE_REQUIRED, 'Sleep time (multiplied by 100 000 µs)', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $art = <<<'ART'
            🦫🦫🦫🦫🦫🦫            🦫🦫🦫          🦫🦫🦫🦫🦫🦫    🦫🦫🦫🦫🦫🦫🦫🦫    🦫🦫🦫🦫🦫🦫🦫    🦫🦫🦫🦫🦫🦫🦫🦫
            🦫🦫        🦫🦫      🦫🦫  🦫🦫      🦫🦫        🦫🦫        🦫🦫        🦫🦫          🦫🦫  🦫🦫          🦫🦫
            🦫🦫                🦫🦫      🦫🦫    🦫🦫                    🦫🦫        🦫🦫          🦫🦫  🦫🦫          🦫🦫
            🦫🦫              🦫🦫          🦫🦫    🦫🦫🦫🦫🦫🦫          🦫🦫        🦫🦫          🦫🦫  🦫🦫🦫🦫🦫🦫🦫🦫
            🦫🦫              🦫🦫🦫🦫🦫🦫🦫🦫🦫              🦫🦫        🦫🦫        🦫🦫          🦫🦫  🦫🦫      🦫🦫
            🦫🦫        🦫🦫  🦫🦫          🦫🦫  🦫🦫        🦫🦫        🦫🦫        🦫🦫          🦫🦫  🦫🦫        🦫🦫
            🦫🦫🦫🦫🦫🦫      🦫🦫          🦫🦫    🦫🦫🦫🦫🦫🦫          🦫🦫          🦫🦫🦫🦫🦫🦫🦫    🦫🦫          🦫🦫
            ART;

        $arts = [];
        foreach ((array) preg_split('//u', $input->getOption('emojis'), -1, \PREG_SPLIT_NO_EMPTY) as $char) {
            $arts[] = str_replace('🦫', (string) $char, $art);
        }

        $cursor = new Cursor($output);
        $cursor->savePosition();

        loop:
        foreach ($arts as $art) {
            $output->write($art);
            usleep($input->getOption('sleep') * 100_000);
            $cursor->restorePosition();
        }
        goto loop;

        return 0;
    }
}
