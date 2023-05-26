<?php

namespace Castor\Console\Command;

use Castor\PathHelper;
use Castor\Stub\StubsGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
class StubsGeneratorCommand extends Command
{
    protected function configure()
    {
        try {
            $dest = PathHelper::getRoot() . '/.castor.stub.php';
        } catch (\RuntimeException) {
            $dest = getcwd() . '/.castor.stub.php';
        }

        $this->setName('self:stubs:generate');
        $this->setDescription('Generate stub files for castor');
        $this->addArgument('dest', InputArgument::OPTIONAL, 'Destination file of the stub', $dest);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new StubsGenerator())->generateStubs($input->getArgument('dest'));

        return 0;
    }
}
