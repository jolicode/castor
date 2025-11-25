<?php

namespace Castor\Console\Command;

use Castor\Attribute\AsSymfonyTask;
use Castor\Console\Input\GetRawTokenTrait;
use Castor\Descriptor\SymfonyTaskDescriptor;
use Castor\Runner\PhpRunner;
use Castor\Runner\ProcessRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Mime\MimeTypes;

/** @internal */
#[Exclude]
class SymfonyTaskCommand extends Command
{
    use GetRawTokenTrait;

    private const OPTIONS_FILTERS = [
        '--help',
        '--quiet',
        '--verbose',
        '--version',
        '--silent',
        '--ansi',
        '--no-ansi',
        '--no-interaction',
    ];

    /**
     * @param mixed[] $definition
     */
    private function __construct(
        public readonly AsSymfonyTask $taskAttribute,
        public readonly \ReflectionClass $class,
        public readonly array $definition,
        public readonly PhpRunner $phpRunner,
        public readonly ProcessRunner $processRunner,
    ) {
        parent::__construct($taskAttribute->name);
    }

    public static function createFromDescriptor(SymfonyTaskDescriptor $symfonyTaskDescriptor, PhpRunner $phpRunner, ProcessRunner $processRunner): self
    {
        return new self(
            $symfonyTaskDescriptor->taskAttribute,
            $symfonyTaskDescriptor->function,
            $symfonyTaskDescriptor->definition,
            $phpRunner,
            $processRunner,
        );
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = $this->taskAttribute->console;

        if (!$console) {
            throw new \RuntimeException('The AsSymfonyTask attribute "console" property must be a non-empty array.');
        }

        $execPath = $console[0];
        $console = \array_slice($console, 1);

        if (null === $this->taskAttribute->usePhpRunner) {
            $mimeTypes = new MimeTypes();
            $mimeType = $mimeTypes->guessMimeType($execPath);

            $mainMimeType = $mimeType ? explode('/', $mimeType)[0] : null;

            if ('text' === $mainMimeType && ($content = file_get_contents($execPath)) !== false) {
                // let's read the file and check for php tags
                $tokens = \PhpToken::tokenize($content);
                $hasPhpTag = false;

                foreach ($tokens as $token) {
                    if (\T_OPEN_TAG === $token->id || \T_OPEN_TAG_WITH_ECHO === $token->id) {
                        $hasPhpTag = true;

                        break;
                    }
                }

                $this->taskAttribute->usePhpRunner = $hasPhpTag;
            } elseif ('application' === $mainMimeType) {
                // check for php mime types
                $this->taskAttribute->usePhpRunner = \in_array($mimeType, ['application/x-php', 'application/php'], true);
            } else {
                $this->taskAttribute->usePhpRunner = false;
            }
        }

        $args = [...$console];

        if ($this->taskAttribute->originalName) {
            $args[] = $this->taskAttribute->originalName;
        }

        $args = [...$args, ...$this->getRawTokens($input)];

        if ($this->taskAttribute->usePhpRunner) {
            $p = $this->phpRunner->run($execPath, $args);
        } else {
            $p = $this->processRunner->run([$execPath, ...$args]);
        }

        return $p->getExitCode() ?? 0;
    }
}
