<?php

namespace Castor\Console;

use Castor\Console\Command\CompileCommand;
use Castor\Console\Command\DebugCommand;
use Castor\Console\Command\RepackCommand;
use Castor\ContextRegistry;
use Castor\EventDispatcher;
use Castor\ExpressionLanguage;
use Castor\Fingerprint\FingerprintHelper;
use Castor\FunctionFinder;
use Castor\HasherHelper;
use Castor\Listener\GenerateStubsListener;
use Castor\Listener\UpdateCastorListener;
use Castor\Monolog\Processor\ProcessProcessor;
use Castor\PathHelper;
use Castor\PlatformHelper;
use Castor\Remote\Composer;
use Castor\Remote\Importer;
use Castor\Remote\Listener\RemoteImportListener;
use Castor\Stub\StubsGenerator;
use Castor\WaitForHelper;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

/** @internal */
class ApplicationFactory
{
    public static function create(): SymfonyApplication
    {
        try {
            $rootDir = PathHelper::getRoot();
        } catch (\RuntimeException $e) {
            return new CastorFileNotFoundApplication($e);
        }

        $class = Application::class;
        if (class_exists(\RepackedApplication::class)) {
            $class = \RepackedApplication::class;
        }

        $contextRegistry = new ContextRegistry();
        $httpClient = HttpClient::create([
            'headers' => [
                'User-Agent' => 'Castor/' . Application::VERSION,
            ],
        ]);
        $cacheDir = PlatformHelper::getCacheDirectory();
        $cache = new FilesystemAdapter(directory: $cacheDir);
        $logger = new Logger('castor', [], [new ProcessProcessor()]);
        $fs = new Filesystem();
        $fingerprintHelper = new FingerprintHelper($cache);
        $importer = new Importer($logger, new Composer($fs, $logger, $fingerprintHelper));
        $eventDispatcher = new EventDispatcher(logger: $logger);
        $eventDispatcher->addSubscriber(new UpdateCastorListener(
            $cache,
            $httpClient,
            $logger,
        ));
        $eventDispatcher->addSubscriber(new GenerateStubsListener(
            new StubsGenerator($rootDir, $logger),
        ));
        $eventDispatcher->addSubscriber(new RemoteImportListener($importer));

        /** @var SymfonyApplication */
        // @phpstan-ignore-next-line
        $application = new $class(
            $rootDir,
            new FunctionFinder($cache, $rootDir),
            $contextRegistry,
            $eventDispatcher,
            new ExpressionLanguage($contextRegistry),
            $logger,
            $fs,
            new WaitForHelper($httpClient, $logger),
            $fingerprintHelper,
            $importer,
            $httpClient,
            $cache,
        );

        // Avoid dependency cycle
        $importer->setApplication($application);

        $application->setDispatcher($eventDispatcher);
        $application->add(new DebugCommand($rootDir, $cacheDir, $contextRegistry));

        if (!class_exists(\RepackedApplication::class)) {
            $application->add(new RepackCommand());
            $application->add(new CompileCommand($httpClient, $fs));
        }

        return $application;
    }
}
