<?php

namespace Castor\Console\Command;

use Castor\PathHelper;
use Castor\PlatformUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
class CompileCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $fs
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('compile')
            ->addArgument('phar-path', InputArgument::REQUIRED, 'Path to phar to compile along PHP')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Compiled standalone binary output filepath', PathHelper::getRoot() . '/castor')
            ->addOption('os', null, InputOption::VALUE_REQUIRED, 'Target OS for PHP compilation', 'linux', ['linux', 'macos'])
            ->addOption('arch', null, InputOption::VALUE_REQUIRED, 'Target architecture for PHP compilation', 'x86_64', ['x86_64', 'aarch64'])
            ->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'PHP version in major.minor format', '8.3')
            ->addOption('php-extensions', null, InputOption::VALUE_REQUIRED, 'PHP extensions required, in a comma-separated format. Defaults are the minimum required to run a basic "Hello World" task in Castor.', 'mbstring,phar,posix,tokenizer')
            ->addOption('php-rebuild', null, InputOption::VALUE_NONE, 'Ignore cache and force PHP build compilation.')
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $os = $input->getOption('os');
        if (!\in_array($os, ['linux', 'macos'])) {
            throw new \InvalidArgumentException('Currently supported target OS are one of "linux" or "macos"');
        }

        $arch = $input->getOption('arch');
        if (!\in_array($arch, ['x86_64', 'aarch64'])) {
            throw new \InvalidArgumentException('Target architecture must be one of "x86_64" or "aarch64"');
        }

        $io = new SymfonyStyle($input, $output);
        $io->section('Compiling PHP and your Castor app phar into a standalone binary');

        $phpBuildCacheKey = $this->generatePHPBuildCacheKey($input);

        $spcBinaryPath = PlatformUtil::getCacheDirectory() . '/castor-php-static-compiler/' . $phpBuildCacheKey . '/spc';
        $spcBinaryDir = \dirname($spcBinaryPath);

        $this->setupSPC(
            $spcBinaryDir,
            $spcBinaryPath,
            $io,
            $os,
            $arch,
            $output
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
                $arch,
                $spcBinaryDir,
                $io
            );
        }

        $this->mergePHPandPHARIntoSingleExecutable(
            $spcBinaryPath,
            $input->getArgument('phar-path'),
            $input->getOption('output'),
            $spcBinaryDir,
            $io
        );

        return Command::SUCCESS;
    }

    private function downloadSPC(string $spcSourceUrl, string $spcBinaryDestination, SymfonyStyle $io): void
    {
        $response = $this->httpClient->request('GET', $spcSourceUrl);
        $contentLength = $response->getHeaders()['content-length'][0] ?? 0;

        $outputStream = fopen($spcBinaryDestination, 'w');
        $progressBar = $io->createProgressBar((int) $contentLength);

        if (false === $outputStream) {
            throw new \RuntimeException(sprintf('Failed to open file "%s" for writing.', $spcBinaryDestination));
        }

        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($outputStream, $chunk->getContent());
            $progressBar->advance(\strlen($chunk->getContent()));
        }

        fclose($outputStream);
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
            ],
            cwd: $spcBinaryDir,
            timeout: null,
        );
        $io->text('Running command: ' . $downloadProcess->getCommandLine());
        $downloadProcess->mustRun(fn ($type, $buffer) => print $buffer);
    }

    private function buildPHP(string $spcBinaryPath, mixed $phpExtensions, mixed $arch, string $spcBinaryDir, SymfonyStyle $io): void
    {
        $buildProcess = new Process(
            command: [
                $spcBinaryPath, 'build', $phpExtensions,
                '--build-micro',
                '--with-micro-fake-cli',
                '--arch=' . $arch,
            ],
            cwd: $spcBinaryDir,
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

    private function setupSPC(string $spcBinaryDir, string $spcBinaryPath, SymfonyStyle $io, mixed $os, mixed $arch, OutputInterface $output): void
    {
        $this->fs->mkdir($spcBinaryDir, 0o755);

        if ($this->fs->exists($spcBinaryPath)) {
            $io->text(sprintf('Using the static-php-cli (spc) tool from "%s"', $spcBinaryPath));
        } else {
            $spcSourceUrl = sprintf('https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-%s-%s', $os, $arch);
            $io->text(sprintf('Downloading the static-php-cli (spc) tool from "%s" to "%s"', $spcSourceUrl, $spcBinaryPath));
            $this->downloadSPC($spcSourceUrl, $spcBinaryPath, $io);
            $io->newLine(2);
        }
    }

    private function generatePHPBuildCacheKey(InputInterface $input): string
    {
        $keyComponents = [];

        foreach (['os', 'arch', 'php-version'] as $phpBuildOption) {
            $keyComponents[$phpBuildOption] = $input->getOption($phpBuildOption);
        }

        $phpExtensions = explode(',', $input->getOption('php-extensions'));
        sort($phpExtensions);

        $keyComponents['php-extensions'] = $phpExtensions;

        $keyComponents['compile-command'] = hash_file('md5', __FILE__);

        return md5(serialize($keyComponents));
    }
}
