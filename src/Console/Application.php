<?php

namespace Castor\Console;

use Castor\Console\Command\RepackCommand;
use Castor\Console\Command\TaskCommand;
use Castor\Context;
use Castor\ContextDescriptor;
use Castor\ContextRegistry;
use Castor\FunctionFinder;
use Castor\GlobalHelper;
use Castor\Monolog\Processor\ProcessProcessor;
use Castor\SectionOutput;
use Castor\PlatformUtil;
use Castor\Stub\StubsGenerator;
use Castor\TaskDescriptor;
use Castor\VerbosityLevel;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;

use function Castor\log;
use function Castor\request;
use function Castor\run;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.8.0';

    public function __construct(
        private readonly string $rootDir,
        private readonly ContextRegistry $contextRegistry = new ContextRegistry(),
        private readonly StubsGenerator $stubsGenerator = new StubsGenerator(),
        private readonly FunctionFinder $functionFinder = new FunctionFinder(),
    ) {
        if (!class_exists(\RepackedApplication::class)) {
            $this->add(new RepackCommand());
        }

        $this->setCatchErrors(true);

        parent::__construct(static::NAME, static::VERSION);
    }

    // We do all the logic as late as possible to ensure the exception handler
    // is registered
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $sectionOutput = new SectionOutput($output);

        GlobalHelper::setApplication($this);
        GlobalHelper::setInput($input);
        GlobalHelper::setSectionOutput($sectionOutput);
        GlobalHelper::setLogger(new Logger(
            'castor',
            [
                new ConsoleHandler($sectionOutput->getConsoleOutput()),
            ],
            [
                new ProcessProcessor(),
            ]
        ));
        GlobalHelper::setContextRegistry($this->contextRegistry);
        GlobalHelper::setupDefaultCache();

        $tasks = $this->initializeApplication();

        GlobalHelper::setInitialContext($this->createContext($input, $output));

        foreach ($tasks as $task) {
            $this->add(new TaskCommand($task->taskAttribute, $task->function));
        }

        return parent::doRun($input, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        GlobalHelper::setCommand($command);

        if ($command instanceof TaskCommand) {
            $context = $this->createContext($input, $output);
            GlobalHelper::setInitialContext($context);
        }

        if (!$command instanceof CompleteCommand && !class_exists(\RepackedApplication::class)) {
            $this->stubsGenerator->generateStubsIfNeeded($this->rootDir . '/.castor.stub.php');
            $this->displayUpdateWarningIfNeeded(new SymfonyStyle($input, $output));
        }

        return parent::doRunCommand($command, $input, $output);
    }

    /**
     * @return TaskDescriptor[]
     */
    private function initializeApplication(): array
    {
        $functionsRootDir = $this->rootDir;
        if (class_exists(\RepackedApplication::class)) {
            $functionsRootDir = \RepackedApplication::ROOT_DIR;
        }

        $this->getDefinition()->addOption(
            new InputOption(
                'trust',
                null,
                InputOption::VALUE_NEGATABLE,
                'Trust all the imported functions from remote resources'
            )
        );

        // Find all potential commands / context
        $functions = $this->functionFinder->findFunctions($functionsRootDir);
        $tasks = [];
        foreach ($functions as $function) {
            if ($function instanceof TaskDescriptor) {
                $tasks[] = $function;
            } elseif ($function instanceof ContextDescriptor) {
                $this->contextRegistry->add($function);
            }
        }

        $this->contextRegistry->setDefaultIfEmpty();

        $contextNames = $this->contextRegistry->getNames();
        if ($contextNames) {
            $this->getDefinition()->addOption(new InputOption(
                'context',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('The context to use (%s)', implode('|', $contextNames)),
                $this->contextRegistry->getDefault(),
                $contextNames,
            ));
        }

        return $tasks;
    }

    private function createContext(InputInterface $input, OutputInterface $output): Context
    {
        try {
            $input->bind($this->getDefinition());
        } catch (ExceptionInterface) {
            // not an issue if parsing gone wrong, we'll just use the default
            // context and it will fail later anyway
        }

        // Occurs when running a native command (like `castor -h`, `castor list`, etc), or if no context is defined
        if (!$input->hasOption('context')) {
            return new Context();
        }

        $context = $this
            ->contextRegistry
            ->get($input->getOption('context'))
        ;

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        return $context;
    }

    private function displayUpdateWarningIfNeeded(SymfonyStyle $symfonyStyle): void
    {
        $latestVersion = GlobalHelper::getCache()->get('latest-version', function (ItemInterface $item): array {
            $item->expiresAfter(3600 * 60 * 24);

            $response = request('GET', 'https://api.github.com/repos/jolicode/castor/releases/latest', [
                'timeout' => 1,
            ]);

            try {
                return $response->toArray();
            } catch (HttpExceptionInterface) {
                return [];
            }
        });

        if (!$latestVersion) {
            log('Failed to fetch latest Castor version from GitHub.');

            return;
        }

        if (version_compare($latestVersion['tag_name'], self::VERSION, '<=')) {
            return;
        }

        $symfonyStyle->block(sprintf('<info>A new Castor version is available</info> (<comment>%s</comment>, currently running <comment>%s</comment>).', $latestVersion['tag_name'], self::VERSION), escape: false);

        if ($pharPath = \Phar::running(false)) {
            $assets = match (true) {
                PlatformUtil::isWindows() || PlatformUtil::isWindowsSubsystemForLinux() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'windows')),
                PlatformUtil::isMacOS() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'darwin')),
                PlatformUtil::isUnix() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'linux')),
                default => [],
            };

            if (!$assets) {
                log('Failed to detect the correct release url adapted to your system.');

                return;
            }

            $latestReleaseUrl = reset($assets)['browser_download_url'] ?? null;

            if (!$latestReleaseUrl) {
                log('Failed to fetch latest phar url.');

                return;
            }

            if (PlatformUtil::isUnix()) {
                $symfonyStyle->block('Run the following command to update Castor:');
                $symfonyStyle->block(sprintf('<comment>curl "%s" -Lso castor && chmod u+x castor && mv castor %s</comment>', $latestReleaseUrl, $pharPath), escape: false);
            } else {
                $symfonyStyle->block(sprintf('Download the latest version at <comment>%s</comment>', $latestReleaseUrl), escape: false);
            }

            $symfonyStyle->newLine();

            return;
        }

        $globalComposerPath = trim(run('composer global config home --quiet', quiet: true, allowFailure: true)->getOutput());

        if ($globalComposerPath && str_contains(__FILE__, $globalComposerPath)) {
            $symfonyStyle->block('Run the following command to update Castor: <comment>composer global update jolicode/castor</comment>', escape: false);
        }
    }
}
