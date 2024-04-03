<?php

namespace Castor;

use Castor\Console\Application;
use Castor\Console\Output\SectionOutput;
use Castor\Fingerprint\FingerprintHelper;
use Castor\Helper\WaitForHelper;
use Castor\Import\Importer;
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
    public function __construct(
        public readonly Application $application,
        public readonly CacheItemPoolInterface&CacheInterface $cache,
        public readonly ContextRegistry $contextRegistry,
        public readonly ContextRegistry $outputInterface,
        public readonly EventDispatcherInterface $eventDispatcher,
        public readonly Filesystem $fs,
        public readonly FingerprintHelper $fingerprintHelper,
        public readonly FunctionFinder $functionFinder,
        public readonly WaitForHelper $waitForHelper,
        public readonly HttpClientInterface $httpClient,
        public readonly Importer $importer,
        public readonly InputInterface $input,
        public readonly LoggerInterface $logger,
        public readonly OutputInterface $output,
        public readonly SectionOutput $sectionOutput,
        public readonly SymfonyStyle $symfonyStyle,
    ) {
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
