<?php

namespace Castor\Console;

use Castor\Console\Command\TaskCommand;
use Castor\Context;
use Castor\ContextDescriptor;
use Castor\ContextRegistry;
use Castor\FunctionFinder;
use Castor\GlobalHelper;
use Castor\Stub\StubsGenerator;
use Castor\TaskDescriptor;
use Castor\VerbosityLevel;
use Joli\JoliNotif\Util\OsHelper;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function Castor\log;
use function Castor\run;

/** @internal */
class Application extends SymfonyApplication
{
    final public const VERSION = 'v0.3.0';

    private readonly CacheInterface $cache;

    public function __construct(
        private readonly string $rootDir,
        private readonly ContextRegistry $contextRegistry = new ContextRegistry(),
        private readonly StubsGenerator $stubsGenerator = new StubsGenerator(),
        private readonly FunctionFinder $functionFinder = new FunctionFinder(),
    ) {
        parent::__construct('castor', self::VERSION);

        $this->cache = new FilesystemAdapter('castor', 0, sys_get_temp_dir() . '/castor');
    }

    // We do all the logic as late as possible to ensure the exception handler
    // is registered
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeApplication();

        // Remove the try/catch when https://github.com/symfony/symfony/pull/50420 is released
        try {
            return parent::doRun($input, $output);
        } catch (\Throwable $e) {
            $this->renderThrowable($e, $output);

            return 1;
        }
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        if ('_complete' !== $command->getName()) {
            $this->stubsGenerator->generateStubsIfNeeded($this->rootDir . '/.castor.stub.php');
            $this->displayUpdateWarningIfNeeded(new SymfonyStyle($input, $output));
        }

        $this->initializeContext($input, $output);

        return parent::doRunCommand($command, $input, $output);
    }

    private function initializeApplication(): void
    {
        // Find all potential commands / context
        $functions = $this->functionFinder->findFunctions($this->rootDir);

        foreach ($functions as $function) {
            if ($function instanceof TaskDescriptor) {
                $this->add(new TaskCommand($function->taskAttribute, $function->function));
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
                $this->contextRegistry->getDefault()->contextAttribute->name,
                $contextNames,
            ));
        }
    }

    private function initializeContext(InputInterface $input, OutputInterface $output): void
    {
        $context = $this->createContext($input, $output);

        if ($context->verbosityLevel->isNotConfigured()) {
            $context = $context->withVerbosityLevel(VerbosityLevel::fromSymfonyOutput($output));
        }

        GlobalHelper::setInitialContext($context);
    }

    private function createContext(InputInterface $input, OutputInterface $output): Context
    {
        // occurs when running `castor -h`, or if no context is defined
        if (!$input->hasOption('context')) {
            return new Context();
        }

        static $supportedParameterTypes = [
            SymfonyStyle::class,
            self::class,
            InputInterface::class,
            OutputInterface::class,
        ];
        $descriptor = $this->contextRegistry->get($input->getOption('context'));

        $args = [];
        foreach ($descriptor->function->getParameters() as $parameter) {
            if (($type = $parameter->getType()) instanceof \ReflectionNamedType && \in_array($type->getName(), $supportedParameterTypes, true)) {
                $args[] = match ($type->getName()) {
                    SymfonyStyle::class => new SymfonyStyle($input, $output),
                    self::class => $this,
                    InputInterface::class => $input,
                    OutputInterface::class => $output,
                    default => throw new \LogicException(sprintf('Argument "%s" is not supported in context builder named "%s".', $parameter->getName(), $descriptor->function->getName())),
                };

                continue;
            }

            throw new \LogicException(sprintf('Argument "%s" is not supported in context builder named "%s".', $parameter->getName(), $descriptor->function->getName()));
        }

        return $descriptor->function->invoke(...$args);
    }

    private function displayUpdateWarningIfNeeded(SymfonyStyle $symfonyStyle): void
    {
        $latestVersion = $this->cache->get('latest-version', function (ItemInterface $item): array {
            $item->expiresAfter(3600 * 60 * 24);
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: castor',
                    'timeout' => 5,
                ],
            ];

            $context = stream_context_create($opts);

            $content = @file_get_contents('https://api.github.com/repos/jolicode/castor/releases/latest', false, $context);

            return json_decode($content ?: '', true) ?? [];
        });

        if (!$latestVersion) {
            log('Failed to fetch latest Castor version from GitHub.');

            return;
        }

        if (version_compare($latestVersion['tag_name'], self::VERSION, '<=')) {
            return;
        }

        $symfonyStyle->block(sprintf('<info>A new Castor version is available</info> (<comment>%s</comment>, currently running <comment>%s</comment>).', $latestVersion['tag_name'], self::VERSION), escape: false);
        $pharPath = '/home/loick/.local/bin/castor';

        if ($pharPath = \Phar::running(false)) {
            $assets = match (true) {
                OsHelper::isWindows() || OsHelper::isWindowsSubsystemForLinux() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'windows')),
                OsHelper::isMacOS() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'darwin')),
                OsHelper::isUnix() => array_filter($latestVersion['assets'], fn (array $asset) => str_contains($asset['name'], 'linux')),
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

            if (OsHelper::isUnix()) {
                $symfonyStyle->block('Run the following command to update Castor:');
                $symfonyStyle->block(sprintf('<comment>curl "%s" --output castor && chmod u+x castor && mv castor %s</comment>', $latestReleaseUrl, $pharPath), escape: false);
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
