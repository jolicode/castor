<?php

namespace Castor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
class CastorFileNotFoundCommand extends SingleCommandApplication
{
    public function __construct(
        private readonly \RuntimeException $e,
    ) {
        parent::__construct('castor');
    }

    protected function configure(): void
    {
        $this->addArgument('options', InputArgument::IS_ARRAY | InputArgument::OPTIONAL);
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $message = $this->e->getMessage();

        if ($input->getArgument('options')) {
            $message .= ' Did you run castor in the right directory?';

            $io->error($message);

            return self::FAILURE;
        }

        $io->warning($message);

        if (!$io->confirm('Do you want to create a new project?', false)) {
            return Command::SUCCESS;
        }

        file_put_contents(
            'castor.php',
            <<<'PHP'
                    <?php

                    use Castor\Attribute\AsTask;

                    #[AsTask()]
                    function hello(): void
                    {
                        echo "Hello world!\n";
                    }
                PHP
        );

        $io->success('Project created. You can edit "castor.php" and write your own tasks.');
        $io->note('Run "castor" to see the available tasks.');

        return Command::SUCCESS;
    }
}
