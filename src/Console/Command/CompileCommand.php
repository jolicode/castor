<?php

namespace Castor\Console\Command;

use Castor\Helper\PathHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal */
class CompileCommand extends Command
{
    // When something **important** related to the compilation changed, increase
    // this version to invalide the cache
    private const CACHE_VERSION = '2';
    private const DEFAULT_SPC_VERSION = '2.3.1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $fs,
        private readonly string $cacheDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('castor:compile')
            ->setAliases(['compile'])
            ->addArgument('phar-path', InputArgument::REQUIRED, 'Path to phar to compile along PHP')
            ->addOption('spc-version', null, InputOption::VALUE_REQUIRED, 'Version of the static-php-cli (spc) tool to use', self::DEFAULT_SPC_VERSION)
            ->addOption('binary-path', null, InputOption::VALUE_REQUIRED, 'Path to compiled static binary. It can be the parent dirname too', PathHelper::getRoot())
            ->addOption('os', null, InputOption::VALUE_REQUIRED, 'Target OS for PHP compilation', 'linux', ['linux', 'macos'])
            ->addOption('arch', null, InputOption::VALUE_REQUIRED, 'Target architecture for PHP compilation', 'x86_64', ['x86_64', 'aarch64'])
            ->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'PHP version in major.minor format', '8.3')
            ->addOption('php-extensions', null, InputOption::VALUE_REQUIRED, 'PHP extensions required, in a comma-separated format. Defaults are the minimum required to run a basic "Hello World" task in Castor.', 'mbstring,phar,posix,tokenizer,curl,filter,openssl')
            ->addOption('php-rebuild', null, InputOption::VALUE_NONE, 'Ignore cache and force PHP build compilation.')
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateInput($input);
        $io = new SymfonyStyle($input, $output);
        $io->section('Compiling PHP and your Castor app phar into a static binary');

        $phpBuildCacheKey = $this->generatePHPBuildCacheKey($input);

        $spcBinaryPath = $this->cacheDir . '/castor-php-static-compiler/' . $phpBuildCacheKey . '/spc';
        $spcBinaryDir = \dirname($spcBinaryPath);

        $os = $input->getOption('os');
        $arch = $input->getOption('arch');

        $this->setupSPC(
            $spcBinaryDir,
            $spcBinaryPath,
            $io,
            $os,
            $arch,
            $input->getOption('spc-version'),
        );

        if (!$this->fs->exists($spcBinaryDir . '/buildroot/bin/micro.sfx') || $input->getOption('php-rebuild')) {
            $this->installPHPBuildTools($spcBinaryPath, $spcBinaryDir, $io);

            $phpExtensions = $input->getOption('php-extensions');

            $this->downloadPHPSourceDeps(
                $spcBinaryPath,
                $phpExtensions,
                $input->getOption('php-version'),
                $spcBinaryDir,
                $io
            );

            $this->buildPHP(
                $spcBinaryPath,
                $phpExtensions,
                $os = $input->getOption('os'),
                ('macos' === $os && 'aarch64' === $arch) ? 'arm64' : $arch,
                $spcBinaryDir,
                $io,
                $output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG,
            );
        }

        $binaryPath = $this->getBinaryPath($input);

        $this->mergePHPandPHARIntoSingleExecutable(
            $spcBinaryPath,
            $input->getArgument('phar-path'),
            $binaryPath,
            $spcBinaryDir,
            $io
        );

        $io->success(\sprintf('Your Castor app has been compiled into a static binary in "%s"', $binaryPath));

        return Command::SUCCESS;
    }

    private function validateInput(InputInterface $input): void
    {
        $os = $input->getOption('os');
        if (!\in_array($os, ['linux', 'macos'])) {
            throw new \InvalidArgumentException('Currently supported target OS are one of "linux" or "macos"');
        }

        $arch = $input->getOption('arch');
        if (!\in_array($arch, ['x86_64', 'aarch64'])) {
            throw new \InvalidArgumentException('Target architecture must be one of "x86_64" or "aarch64"');
        }

        if (!is_file($input->getArgument('phar-path'))) {
            throw new \InvalidArgumentException(\sprintf('The phar file "%s" does not exist.', $input->getArgument('phar-path')));
        }

        $input->setArgument('phar-path', Path::makeAbsolute($input->getArgument('phar-path'), getcwd() ?: PathHelper::getRoot()));
    }

    private function downloadSPC(string $spcSourceUrl, string $spcBinaryDestination, SymfonyStyle $io): void
    {
        $response = $this->httpClient->request('GET', $spcSourceUrl);
        $contentLength = $response->getHeaders()['content-length'][0] ?? 0;

        $spcTarGzDestination = $spcBinaryDestination . '.tar.gz';
        $outputStream = fopen($spcTarGzDestination, 'w');
        $progressBar = $io->createProgressBar((int) $contentLength);

        if (false === $outputStream) {
            throw new \RuntimeException(\sprintf('Failed to open file "%s" for writing.', $spcBinaryDestination));
        }

        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($outputStream, $chunk->getContent());
            $progressBar->advance(\strlen($chunk->getContent()));
        }

        fclose($outputStream);

        $extractProcess = new Process(
            command: ['tar', 'xf', $spcTarGzDestination],
            cwd: \dirname($spcBinaryDestination),
            timeout: null,
        );

        $io->text('Running command: ' . $extractProcess->getCommandLine());
        $extractProcess->mustRun(fn ($type, $buffer) => print $buffer);
        chmod($spcBinaryDestination, 0o755);

        $progressBar->finish();
    }

    private function installPHPBuildTools(string $spcBinaryPath, string $spcBinaryDir, SymfonyStyle $io): void
    {
        $installSPCDepsProcess = new Process(
            command: [$spcBinaryPath, 'doctor', '--auto-fix'],
            cwd: $spcBinaryDir,
            timeout: null,
        );
        $io->text('Running command: ' . $installSPCDepsProcess->getCommandLine());
        $installSPCDepsProcess->mustRun(fn ($type, $buffer) => print $buffer);
    }

    private function downloadPHPSourceDeps(string $spcBinaryPath, mixed $phpExtensions, mixed $phpVersion, string $spcBinaryDir, SymfonyStyle $io): void
    {
        $downloadProcess = new Process(
            command: [
                $spcBinaryPath, 'download',
                '--for-extensions=' . $phpExtensions,
                '--with-php=' . $phpVersion,
                '--prefer-pre-built',
            ],
            cwd: $spcBinaryDir,
            timeout: null,
        );
        $io->text('Running command: ' . $downloadProcess->getCommandLine());
        $downloadProcess->mustRun(fn ($type, $buffer) => print $buffer);
    }

    private function buildPHP(string $spcBinaryPath, mixed $phpExtensions, mixed $os, mixed $arch, string $spcBinaryDir, SymfonyStyle $io, bool $debug = false): void
    {
        $command = [
            $spcBinaryPath, 'build', $phpExtensions,
            '--build-micro',
            '--with-micro-fake-cli',
            '--arch=' . $arch,
        ];

        if ($debug) {
            $command[] = '--debug';
        }

        $buildProcess = new Process(
            command: $command,
            cwd: $spcBinaryDir,
            env: ('linux' === $os) ? [
                'OPENSSL_LIBS' => '-l:libssl.a -l:libcrypto.a -ldl -lpthread',
                'OPENSSL_CFLAGS' => \sprintf('-I%s/source/openssl/include', $spcBinaryDir),
            ] : [],
            timeout: null,
        );
        $io->text('Running command: ' . $buildProcess->getCommandLine());
        $buildProcess->mustRun(fn ($type, $buffer) => print $buffer);
    }

    private function mergePHPandPHARIntoSingleExecutable(string $spcBinaryPath, string $pharFilePath, string $appBinaryFilePath, string $spcBinaryDir, SymfonyStyle $io): void
    {
        if (!$this->fs->isAbsolutePath($pharFilePath)) {
            $pharFilePath = PathHelper::getRoot() . '/' . $pharFilePath;
        }

        $mergePHPandPHARProcess = new Process(
            [
                $spcBinaryPath,
                'micro:combine', $pharFilePath,
                '--output=' . $appBinaryFilePath,
            ],
            cwd: $spcBinaryDir,
            timeout: null,
        );

        $io->text('Running command: ' . $mergePHPandPHARProcess->getCommandLine());
        $mergePHPandPHARProcess->mustRun(fn ($type, $buffer) => print $buffer);
    }

    private function setupSPC(string $spcBinaryDir, string $spcBinaryPath, SymfonyStyle $io, mixed $os, mixed $arch, string $spcVersion): void
    {
        $this->fs->mkdir($spcBinaryDir, 0o755);

        if ($this->fs->exists($spcBinaryPath)) {
            $io->text(\sprintf('Using the static-php-cli (spc) tool from "%s"', $spcBinaryPath));
        } else {
            $spcSourceUrl = \sprintf('https://github.com/crazywhalecc/static-php-cli/releases/download/%s/spc-%s-%s.tar.gz', $spcVersion, $os, $arch);
            $io->text(\sprintf('Downloading the static-php-cli (spc) tool from "%s" to "%s"', $spcSourceUrl, $spcBinaryPath));
            $this->downloadSPC($spcSourceUrl, $spcBinaryPath, $io);
            $io->newLine(2);
        }
    }

    private function getBinaryPath(InputInterface $input): string
    {
        $binaryPath = $input->getOption('binary-path');
        if (!Path::isAbsolute($binaryPath)) {
            $binaryPath = Path::makeAbsolute($binaryPath, getcwd() ?: PathHelper::getRoot());
        }

        if (!is_dir($binaryPath)) {
            return $binaryPath;
        }

        $p = new Process([\PHP_BINARY, $input->getArgument('phar-path'), 'list', '--format', 'json']);
        $p->mustRun();
        $appName = json_decode($p->getOutput(), true)['application']['name'];

        return \sprintf(
            '%s/%s.%s.%s',
            $binaryPath,
            $appName,
            $input->getOption('os'),
            $input->getOption('arch'),
        );
    }

    private function generatePHPBuildCacheKey(InputInterface $input): string
    {
        $c = hash_init('sha256');

        foreach (['os', 'arch', 'php-version', 'php-extensions'] as $phpBuildOption) {
            hash_update($c, $input->getOption($phpBuildOption));
        }

        $phpExtensions = explode(',', $input->getOption('php-extensions'));
        sort($phpExtensions);
        hash_update($c, implode(',', $phpExtensions));

        hash_update($c, self::CACHE_VERSION);
        hash_update($c, $input->getOption('spc-version'));

        return hash_final($c);
    }
}
