<?php

namespace Castor\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
#[AsCommand(
    name: 'init',
    description: 'Initializes a new Castor project',
)]
class InitCommand extends Command
{
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Only ask for confirmation if not running "castor init" explicitly
        if ($this->getName() !== $input->getFirstArgument() && !$io->confirm('Do you want to initialize current directory with castor? This will create an initial "castor.php" file.', false)) {
            $io->note('Doing nothing.');

            return Command::SUCCESS;
        }

        file_put_contents(
            'castor.php',
            <<<'PHP'
                <?php

                use Castor\Attribute\AsTask;

                use function Castor\io;
                use function Castor\capture;

                #[AsTask(description: 'Welcome to Castor!')]
                function hello(): void
                {
                    $currentUser = capture('whoami');

                    io()->title(sprintf('Hello %s!', $currentUser));
                }
                PHP
        );

        if (is_file('.gitignore')) {
            file_put_contents('.gitignore', "\n.castor.stub.php\n", \FILE_APPEND);
        }

        $io->success('A "castor.php" file has been created in the current directory. You can now edit it and write your own tasks.');
        $io->note('Learn how to get started with Castor by reading the documentation at https://castor.jolicode.com/docs/getting-started');

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('options', InputArgument::IS_ARRAY | InputArgument::OPTIONAL);
        $this->ignoreValidationErrors();
    }
}
