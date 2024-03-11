<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsSymfonyTask;
use Castor\Console\Input\Input;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** @internal */
class SymfonyTaskCommand extends Command
{
    private const OPTIONS_FILTERS = [
        '--help',
        '--quiet',
        '--verbose',
        '--version',
        '--ansi',
        '--no-ansi',
        '--no-interaction',
    ];

    /**
     * @param mixed[] $definition
     */
    public function __construct(
        public readonly AsSymfonyTask $taskAttribute,
        public readonly \ReflectionClass $class,
        public readonly array $definition,
    ) {
        parent::__construct($taskAttribute->name);
    }

    protected function configure(): void
    {
        $this->setAliases($this->definition['aliases'] ?? []);
        $this->setDescription($this->definition['description']);
        $this->setHelp($this->definition['help']);
        $this->setHidden($this->definition['hidden']);

        foreach ($this->definition['definition']['arguments'] as $argument) {
            $this->addArgument(
                $argument['name'],
                ($argument['is_required'] ? InputArgument::REQUIRED : InputArgument::OPTIONAL)
                    | ($argument['is_array'] ? InputArgument::IS_ARRAY : 0),
                $argument['description'],
                $argument['default'],
            );
        }
        foreach ($this->definition['definition']['options'] as $option) {
            if (\in_array($option['name'], self::OPTIONS_FILTERS, true)) {
                continue;
            }

            $this->addOption(
                $option['name'],
                $option['shortcut'],
                ($option['accept_value'] ? 0 : InputOption::VALUE_NONE)
                    | ($option['is_value_required'] ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL)
                    | ($option['is_multiple'] ? InputOption::VALUE_IS_ARRAY : 0),
                $option['description'],
                $option['accept_value'] ? $option['default'] : null,
            );
        }
    }

    /**
     * @param Input $input
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $extra = array_filter($input->getRawTokens(), fn ($item) => $item !== $this->taskAttribute->name);

        $p = new Process([...$this->taskAttribute->console, $this->taskAttribute->originalName, ...$extra]);
        $p->run(fn ($type, $bytes) => print ($bytes));

        return $p->getExitCode() ?? 0;
    }
}
