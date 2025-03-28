<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Console\Output\SectionOutput;
use Castor\Fingerprint\FingerprintHelper;
use Castor\Helper\Notifier;
use Castor\Helper\SymmetricCrypto;
use Castor\Helper\Waiter;
use Castor\Helper\ZipArchiver;
use Castor\Http\HttpDownloader;
use Castor\Import\Importer;
use Castor\Runner\ParallelRunner;
use Castor\Runner\ProcessRunner;
use Castor\Runner\SshRunner;
use Castor\Runner\WatchRunner;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal */
final class Container
{
    private static self $instance;

    public function __construct(
        public readonly Application $application,
        public readonly CacheItemPoolInterface&CacheInterface $cache,
        public readonly ContextRegistry $contextRegistry,
        public readonly ContextRegistry $outputInterface,
        public readonly EventDispatcherInterface $eventDispatcher,
        public readonly Filesystem $fs,
        public readonly FingerprintHelper $fingerprintHelper,
        public readonly HttpClientInterface $httpClient,
        public readonly HttpDownloader $httpDownloader,
        public readonly Importer $importer,
        public readonly InputInterface $input,
        public readonly Kernel $kernel,
        public readonly LoggerInterface $logger,
        public readonly Notifier $notifier,
        public readonly OutputInterface $output,
        public readonly ParallelRunner $parallelRunner,
        public readonly ProcessRunner $processRunner,
        public readonly SectionOutput $sectionOutput,
        public readonly SshRunner $sshRunner,
        public readonly SymfonyStyle $symfonyStyle,
        public readonly Waiter $waiter,
        public readonly WatchRunner $watchRunner,
        public readonly SymmetricCrypto $symmetricCrypto,
        public readonly ZipArchiver $zipArchiver,
    ) {
    }

    public static function set(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function get(): self
    {
        return self::$instance ?? throw new \LogicException('Container not initialized yet.');
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public function getCommand(bool $allowNull = false): ?Command
    {
        return $this->application->getCommand($allowNull);
    }

    public function getContext(?string $name = null): Context
    {
        return $this->contextRegistry->get($name);
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->contextRegistry->getVariable($key, $default);
    }
}
