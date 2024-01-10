<?php

namespace Castor\Console;

use Castor\ContextRegistry;
use Castor\EventDispatcher;
use Castor\ExpressionLanguage;
use Castor\Fingerprint\FingerprintHelper;
use Castor\FunctionFinder;
use Castor\Monolog\Processor\ProcessProcessor;
use Castor\PathHelper;
use Castor\PlatformUtil;
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
        $cache = new FilesystemAdapter(directory: PlatformUtil::getCacheDirectory());
        $logger = new Logger('castor', [], [new ProcessProcessor()]);

        // @phpstan-ignore-next-line
        return new $class(
            $rootDir,
            new FunctionFinder(),
            $contextRegistry,
            new EventDispatcher(logger: $logger),
            new ExpressionLanguage($contextRegistry),
            new StubsGenerator($logger),
            $logger,
            new Filesystem(),
            $httpClient,
            $cache,
            new WaitForHelper($httpClient, $logger),
            new FingerprintHelper($cache),
        );
    }
}
