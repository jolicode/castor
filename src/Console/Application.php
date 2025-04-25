<?php

namespace Castor\Console;

use Castor\Container;
use Castor\Exception\ProblemException;
use Castor\Kernel;
use Castor\Runner\ProcessRunner;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Process\Exception\ProcessFailedException;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.24.0';

    private Command $command;

    public function __construct(
        private readonly ContainerBuilder $containerBuilder,
        private readonly Kernel $kernel,
        #[Autowire(lazy: true)]
        private readonly SymfonyStyle $io,
        #[Autowire(lazy: true)]
        private readonly ProcessRunner $processRunner,
        #[Autowire('%test%')]
        public readonly bool $test,
    ) {
        parent::__construct(static::NAME, static::VERSION);
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public function getCommand(bool $allowNull = false): ?Command
    {
        return $this->command ?? ($allowNull ? null : throw new \LogicException('Command not available yet.'));
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->containerBuilder->set(InputInterface::class, $input);
        $this->containerBuilder->set(OutputInterface::class, $output);

        // @phpstan-ignore-next-line
        Container::set($this->containerBuilder->get(Container::class));

        $this->kernel->boot($input, $output);

        return parent::doRun($input, $output);
    }

    public function getHelp(): string
    {
        return $this->getLogo() . parent::getHelp();
    }

    public function renderThrowable(\Throwable $e, OutputInterface $output): void
    {
        if (!$output->isVerbose()) {
            $this->enhanceException($e);

            if ($e instanceof ProblemException) {
                $this->io->error($e->getMessage());

                return;
            }

            if ($e instanceof ProcessFailedException) {
                $process = $e->getProcess();
                $runnable = $this->processRunner->buildRunnableCommand($process);

                $this->io->writeln(\sprintf('<comment>%s</comment>', OutputFormatter::escape(\sprintf('In %s line %s:', basename($e->getFile()) ?: 'n/a', $e->getLine() ?: 'n/a'))));
                $this->io->error('The following process did not finish successfully (exit code ' . $process->getExitCode() . '):');
                $this->io->writeln("<fg=yellow>{$runnable}</>");
                $this->io->newLine();

                return;
            }
        }

        parent::renderThrowable($e, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $this->command = $command;

        return parent::doRunCommand($command, $input, $output);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(
            new InputOption(
                'no-remote',
                null,
                InputOption::VALUE_NONE,
                'Skip the import of all remote remote packages',
            )
        );

        $definition->addOption(
            new InputOption(
                'update-remotes',
                null,
                InputOption::VALUE_NONE,
                'Force the update of remote packages',
            )
        );

        return $definition;
    }

    private function enhanceException(\Throwable $exception): void
    {
        if ($exception instanceof \Error) {
            return;
        }

        $castorDirs = [
            \dirname(__DIR__, 1),
            \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'vendor',
            \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'bin',
            // Useful for the phar, or static binary
            $_SERVER['SCRIPT_NAME'],
        ];

        foreach ($exception->getTrace() as $frame) {
            if (!\array_key_exists('file', $frame) || !\array_key_exists('line', $frame)) {
                continue;
            }

            foreach ($castorDirs as $dir) {
                if (str_starts_with($frame['file'], (string) $dir)) {
                    continue 2;
                }
            }

            (new \ReflectionProperty(\Exception::class, 'file'))->setValue($exception, $frame['file']);
            (new \ReflectionProperty(\Exception::class, 'line'))->setValue($exception, $frame['line']);

            break;
        }
    }

    private function getLogo(): string
    {
        if (!$this->test) {
            $now = new \DateTime();
            $year = date('Y');

            // Halloween
            if ($now > new \DateTime($year . '-10-20') && $now < new \DateTime($year . '-11-02')) {
                return <<<'LOGO'

                     ▄████▄   ▄▄▄        ██████ ▄▄▄█████▓ ▒█████   ██▀███
                    ▒██▀ ▀█  ▒████▄    ▒██    ▒ ▓  ██▒ ▓▒▒██▒  ██▒▓██ ▒ ██▒
                    ▒▓█    ▄ ▒██  ▀█▄  ░ ▓██▄   ▒ ▓██░ ▒░▒██░  ██▒▓██ ░▄█ ▒
                    ▒▓▓▄ ▄██▒░██▄▄▄▄██   ▒   ██▒░ ▓██▓ ░ ▒██   ██░▒██▀▀█▄
                    ▒ ▓███▀ ░ ▓█   ▓██▒▒██████▒▒  ▒██▒ ░ ░ ████▓▒░░██▓ ▒██▒
                    ░ ░▒ ▒  ░ ▒▒   ▓▒█░▒ ▒▓▒ ▒ ░  ▒ ░░   ░ ▒░▒░▒░ ░ ▒▓ ░▒▓░
                      ░  ▒     ▒   ▒▒ ░░ ░▒  ░ ░    ░      ░ ▒ ▒░   ░▒ ░ ▒░
                    ░          ░   ▒   ░  ░  ░    ░      ░ ░ ░ ▒    ░░   ░
                    ░ ░            ░  ░      ░               ░ ░     ░
                    ░


                    LOGO;
            }

            // April fool
            if ('04-01' === $now->format('m-d')) {
                return <<<'LOGO'

                    ooo        ooooo           oooo                   .o88o.  o8o  oooo
                    `88.       .888'           `888                   888 `"  `"'  `888
                     888b     d'888   .oooo.    888  oooo   .ooooo.  o888oo  oooo   888   .ooooo.
                     8 Y88. .P  888  `P  )88b   888 .8P'   d88' `88b  888    `888   888  d88' `88b
                     8  `888'   888   .oP"888   888888.    888ooo888  888     888   888  888ooo888
                     8    Y     888  d8(  888   888 `88b.  888    .o  888     888   888  888    .o
                    o8o        o888o `Y888""8o o888o o888o `Y8bod8P' o888o   o888o o888o `Y8bod8P'


                    LOGO;
            }
        }

        return <<<'LOGO'

               █████████                     █████
              ███░░░░░███                   ░░███
             ███     ░░░   ██████    █████  ███████    ██████  ████████
            ░███          ░░░░░███  ███░░  ░░░███░    ███░░███░░███░░███
            ░███           ███████ ░░█████   ░███    ░███ ░███ ░███ ░░░
            ░░███     ███ ███░░███  ░░░░███  ░███ ███░███ ░███ ░███
             ░░█████████ ░░████████ ██████   ░░█████ ░░██████  █████
              ░░░░░░░░░   ░░░░░░░░ ░░░░░░     ░░░░░   ░░░░░░  ░░░░░


            LOGO;
    }
}
