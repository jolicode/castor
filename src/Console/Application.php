<?php

namespace Castor\Console;

use Castor\Console\Command\SymfonyTaskCommand;
use Castor\Console\Command\TaskCommand;
use Castor\Context;
use Castor\ContextDescriptor;
use Castor\ContextGeneratorDescriptor;
use Castor\ContextRegistry;
use Castor\Event\AfterApplicationInitializationEvent;
use Castor\EventDispatcher;
use Castor\ExpressionLanguage;
use Castor\Fingerprint\FingerprintHelper;
use Castor\FunctionFinder;
use Castor\GlobalHelper;
use Castor\HttpHelper;
use Castor\ListenerDescriptor;
use Castor\PlatformUtil;
use Castor\SectionOutput;
use Castor\Stub\StubsGenerator;
use Castor\SymfonyTaskDescriptor;
use Castor\TaskDescriptor;
use Castor\TaskDescriptorCollection;
use Castor\VerbosityLevel;
use Castor\WaitForHelper;
use JoliCode\PhpOsHelper\OsHelper;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LogLevel;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.12.1';

    // "Current" objects availables at some point of the lifecycle
    private InputInterface $input;
    private SectionOutput $sectionOutput;
    private SymfonyStyle $symfonyStyle;
    private Command $command;

    public function __construct(
        private readonly string $rootDir,
        public readonly FunctionFinder $functionFinder,
        public readonly ContextRegistry $contextRegistry,
        public readonly EventDispatcher $eventDispatcher,
        public readonly ExpressionLanguage $expressionLanguage,
        public readonly StubsGenerator $stubsGenerator,
        public readonly Logger $logger,
        public readonly Filesystem $fs,
        public HttpClientInterface $httpClient,
        public CacheItemPoolInterface&CacheInterface $cache,
        public WaitForHelper $waitForHelper,
        public HttpHelper $httpHelper,
        public FingerprintHelper $fingerprintHelper,
    ) {
        $handler = ErrorHandler::register();
        $handler->setDefaultLogger($logger, [
            \E_COMPILE_WARNING => LogLevel::WARNING,
            \E_CORE_WARNING => LogLevel::WARNING,
            \E_USER_WARNING => LogLevel::WARNING,
            \E_WARNING => LogLevel::WARNING,
            \E_USER_DEPRECATED => LogLevel::WARNING,
            \E_DEPRECATED => LogLevel::WARNING,
            \E_USER_NOTICE => LogLevel::WARNING,
            \E_NOTICE => LogLevel::WARNING,

            \E_COMPILE_ERROR => LogLevel::ERROR,
            \E_CORE_ERROR => LogLevel::ERROR,
            \E_ERROR => LogLevel::ERROR,
            \E_PARSE => LogLevel::ERROR,
            \E_RECOVERABLE_ERROR => LogLevel::ERROR,
            \E_STRICT => LogLevel::ERROR,
            \E_USER_ERROR => LogLevel::ERROR,
        ]);

        $this->setCatchErrors(true);

        AbstractCloner::$defaultCasters[self::class] = ['Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'];
        AbstractCloner::$defaultCasters[AfterApplicationInitializationEvent::class] = ['Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'];

        parent::__construct(static::NAME, static::VERSION);

        GlobalHelper::setApplication($this);
    }

    public function getInput(): InputInterface
    {
        return $this->input ?? throw new \LogicException('Input not available yet.');
    }

    public function getSectionOutput(): SectionOutput
    {
        return $this->sectionOutput ?? throw new \LogicException('Section output not available yet.');
    }

    public function getOutput(): OutputInterface
    {
        return $this->getSectionOutput()->getConsoleOutput();
    }

    public function getSymfonyStyle(): SymfonyStyle
    {
        return $this->symfonyStyle ?? throw new \LogicException('SymfonyStyle not available yet.');
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public function getCommand(bool $allowNull = false): ?Command
    {
        return $this->command ?? ($allowNull ? null : throw new \LogicException('Command not available yet.'));
    }

    // We do all the logic as late as possible to ensure the exception handler
    // is registered
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->sectionOutput = new SectionOutput($output);
        $this->symfonyStyle = new SymfonyStyle($input, $output);
        $this->logger->pushHandler(new ConsoleHandler($output));

        $descriptors = $this->initializeApplication($input);

        // Must be done after the initializeApplication() call, to ensure all
        // contexts have been created; but before the adding of task, because we
        // may want to seek in the context to know if the command is enabled
        $this->configureContext($input, $output);

        $event = new AfterApplicationInitializationEvent($this, $descriptors);
        $this->eventDispatcher->dispatch($event);
        $descriptors = $event->taskDescriptorCollection;

        foreach ($descriptors->taskDescriptors as $taskDescriptor) {
            $this->add(new TaskCommand(
                $taskDescriptor->taskAttribute,
                $taskDescriptor->function,
                $this->eventDispatcher,
                $this->expressionLanguage,
            ));
        }
        foreach ($descriptors->symfonyTaskDescriptors as $symfonyTaskDescriptor) {
            $this->add(new SymfonyTaskCommand(
                $symfonyTaskDescriptor->taskAttribute,
                $symfonyTaskDescriptor->function,
                $symfonyTaskDescriptor->definition,
            ));
        }

        return parent::doRun($input, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $this->command = $command;

        if ('_complete' !== $command->getName() && !class_exists(\RepackedApplication::class)) {
            $this->stubsGenerator->generateStubsIfNeeded($this->rootDir . '/.castor.stub.php');
            $this->displayUpdateWarningIfNeeded(new SymfonyStyle($input, $output));
        }

        return parent::doRunCommand($command, $input, $output);
    }

    private function initializeApplication(InputInterface $input): TaskDescriptorCollection
    {
        $functionsRootDir = $this->rootDir;
        if (class_exists(\RepackedApplication::class)) {
            $functionsRootDir = \RepackedApplication::ROOT_DIR;
        }

        $descriptors = $this->functionFinder->findFunctions($functionsRootDir);
        $tasks = [];
        $symfonyTasks = [];
        foreach ($descriptors as $descriptor) {
            if ($descriptor instanceof TaskDescriptor) {
                $tasks[] = $descriptor;
            } elseif ($descriptor instanceof SymfonyTaskDescriptor) {
                $symfonyTasks[] = $descriptor;
            } elseif ($descriptor instanceof ContextDescriptor) {
                $this->contextRegistry->addDescriptor($descriptor);
            } elseif ($descriptor instanceof ContextGeneratorDescriptor) {
                foreach ($descriptor->generators as $name => $generator) {
                    $this->contextRegistry->addContext($name, $generator);
                }
            } elseif ($descriptor instanceof ListenerDescriptor && null !== $descriptor->reflectionFunction->getClosure()) {
                $this->eventDispatcher->addListener(
                    $descriptor->asListener->event,
                    $descriptor->reflectionFunction->getClosure(),
                    $descriptor->asListener->priority
                );
            }
        }

        $this->contextRegistry->setDefaultIfEmpty();

        $contextNames = $this->contextRegistry->getNames();

        if ($contextNames) {
            $defaultContext = PlatformUtil::getEnv('CASTOR_CONTEXT') ?: $this->contextRegistry->getDefaultName();

            $this->getDefinition()->addOption(new InputOption(
                'context',
                '_complete' === $input->getFirstArgument() || 'list' === $input->getFirstArgument() ? null : 'c',
                InputOption::VALUE_REQUIRED,
                sprintf('The context to use (%s)', implode('|', $contextNames)),
                $defaultContext,
                $contextNames,
            ));
        }

        return new TaskDescriptorCollection($tasks, $symfonyTasks);
    }

    private function configureContext(InputInterface $input, OutputInterface $output): void
    {
        try {
            $input->bind($this->getDefinition());
        } catch (ExceptionInterface) {
            // not an issue if parsing gone wrong, we'll just use the default
            // context and it will fail later anyway
        }

        // occurs when running `castor -h`, or if no context is defined
        if (!$input->hasOption('context')) {
            $this->contextRegistry->setCurrentContext(new Context());

            return;
        }

        $context = $this
            ->contextRegistry
            ->get($input->getOption('context'))
        ;

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        $this->contextRegistry->setCurrentContext($context->withName($input->getOption('context')));
    }

    private function displayUpdateWarningIfNeeded(SymfonyStyle $symfonyStyle): void
    {
        $item = $this->cache->getItem('last-update-warning');
        if ($item->isHit()) {
            return;
        }

        // We save it right now, even if there are some failures later. We don't
        // want to waste bandwidth or CPU usage, nor log too much information.
        $item->expiresAfter(60 * 60 * 24);
        $item->set(true);
        $this->cache->save($item);

        $response = $this->httpClient->request('GET', 'https://api.github.com/repos/jolicode/castor/releases/latest', [
            'timeout' => 1,
        ]);

        try {
            $latestVersion = $response->toArray();
        } catch (HttpExceptionInterface) {
            $this->logger->info('Failed to fetch latest Castor version from GitHub.');

            return;
        }

        if (version_compare($latestVersion['tag_name'], self::VERSION, '<=')) {
            return;
        }

        $symfonyStyle->block(sprintf('<info>A new Castor version is available</info> (<comment>%s</comment>, currently running <comment>%s</comment>).', $latestVersion['tag_name'], self::VERSION), escape: false);

        if ($pharPath = \Phar::running(false)) {
            $assets = match (true) {
                OsHelper::isWindows() || OsHelper::isWindowsSubsystemForLinux() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'windows')),
                OsHelper::isMacOS() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'darwin')),
                OsHelper::isUnix() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'linux')),
                default => [],
            };

            if (!$assets) {
                $this->logger->info('Failed to detect the correct release url adapted to your system.');

                return;
            }

            $latestReleaseUrl = reset($assets)['browser_download_url'] ?? null;

            if (!$latestReleaseUrl) {
                $this->logger->info('Failed to fetch latest phar url.');

                return;
            }

            if (OsHelper::isUnix()) {
                $symfonyStyle->block('Run the following command to update Castor:');
                $symfonyStyle->block(sprintf('<comment>curl "%s" -Lfso castor && chmod u+x castor && mv castor %s</comment>', $latestReleaseUrl, $pharPath), escape: false);
            } else {
                $symfonyStyle->block(sprintf('Download the latest version at <comment>%s</comment>', $latestReleaseUrl), escape: false);
            }

            $symfonyStyle->newLine();

            return;
        }

        $process = new Process(['composer', 'global', 'config', 'home', '--quiet']);
        $process->run();
        $globalComposerPath = trim($process->getOutput());

        if ($globalComposerPath && str_contains(__FILE__, $globalComposerPath)) {
            $symfonyStyle->block('Run the following command to update Castor: <comment>composer global update jolicode/castor</comment>', escape: false);
        }
    }
}
