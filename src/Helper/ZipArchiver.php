<?php

namespace Castor\Helper;

use Castor\Context;
use Castor\Runner\ProcessRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ZipArchiver
{
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function zip(
        string $source,
        string $destination,
        #[\SensitiveParameter] ?string $password = null,
        CompressionMethod $compressionMethod = CompressionMethod::DEFLATE,
        int $compressionLevel = 6,
        bool $overwrite = false,
    ): void {
        $zipBinaryAvailable = CompressionMethod::ZSTD !== $compressionMethod && $this->isZipBinaryAvailable();
        $zipExtensionAvailable = class_exists(\ZipArchive::class);

        // The zip binary gets the password as a command line argument, readable
        // by every user of the machine while the archive is created: PHP keeps
        // it private, so it comes first when there is a password
        if ($zipExtensionAvailable && (null !== $password || !$zipBinaryAvailable)) {
            if (!$zipBinaryAvailable) {
                $this->logger->notice('Native zip binary not available or ZSTD compression method is requested, falling back to PHP ZipArchive');
            }

            $this->zipWithPhp($source, $destination, $password, $compressionMethod, $compressionLevel, $overwrite);

            return;
        }

        if ($zipBinaryAvailable) {
            $this->zipWithBinary($source, $destination, $password, $compressionMethod, $compressionLevel, $overwrite);

            return;
        }

        throw new \RuntimeException('No ZIP compression method available. Install zip binary or PHP zip extension.');
    }

    public function zipWithBinary(
        string $source,
        string $destination,
        #[\SensitiveParameter] ?string $password = null,
        CompressionMethod $compressionMethod = CompressionMethod::DEFLATE,
        int $compressionLevel = 6,
        bool $overwrite = false,
    ): void {
        if (!$overwrite && $this->filesystem->exists($destination)) {
            throw new \RuntimeException(\sprintf('Destination file already exists: %s. Use overwrite=true to force overwrite.', $destination));
        }

        $zipCommand = ['zip', '-r', $destination, '-Z', $compressionMethod->value, '-' . $compressionLevel];

        if (null !== $password) {
            // The zip binary only takes a password as an argument (-e reads it
            // from the terminal, not from stdin), so it shows up in the process
            // list until the archive is created
            $this->logger->warning('The password is passed to the zip binary as a command line argument, readable by every user of the machine while the archive is created. Use zip_php() to keep it private.');

            $zipCommand = [...$zipCommand, '-P', $password];
        }

        // There's no equivalent to an overwrite mode in zip binary, use php to remove existing archive
        if ($overwrite) {
            $this->filesystem->remove($destination);
        }

        // basename($source) and setting the working directory to the parent directory of the source prevents
        // the full path structure from being included in the zip file
        $this->processRunner->run(
            [...$zipCommand, basename($source)],
            context: new Context(workingDirectory: \dirname($source), quiet: true)
        );
    }

    public function zipWithPhp(
        string $source,
        string $destination,
        #[\SensitiveParameter] ?string $password = null,
        CompressionMethod $compressionMethod = CompressionMethod::DEFLATE,
        int $compressionLevel = 6,
        bool $overwrite = false,
    ): void {
        $zip = new \ZipArchive();

        if (true !== $zip->open($destination, \ZipArchive::CREATE | ($overwrite ? \ZipArchive::OVERWRITE : \ZipArchive::EXCL))) {
            throw new \RuntimeException(\sprintf('Failed to open zip file for writing: %s. Error: %s', $destination, $zip->getStatusString()));
        }

        if (null !== $password) {
            $zip->setPassword($password);
        }

        if (is_dir($source)) {
            // For directories, we need to manually add each file
            $baseDir = rtrim($source, '/\\');
            $baseName = basename($baseDir);
            if ('.' === $baseName) {
                $baseName = '';
            }

            $zip->addEmptyDir($baseName);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = $baseName . '/' . Path::makeRelative($filePath, $baseDir);
                    $relativePath = ltrim($relativePath, '/\\');

                    $zip->addFile($filePath, $relativePath);
                }
            }
        } else {
            $fileName = basename($source);
            $zip->addFile($source, $fileName);
        }

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);

            if (false === $stat) {
                throw new \RuntimeException(\sprintf('Failed to get stat for index "%d"', $i));
            }

            $fileName = $stat['name'];

            // Skip compression and encryption for directories (names ending with '/')
            // match zip binary behavior
            if (str_ends_with($fileName, '/')) {
                continue;
            }

            $zip->setCompressionName($fileName, $compressionMethod->toZipArchiveMethod(), $compressionLevel);

            if (null !== $password) {
                // Use AES-256 encryption for maximum security
                $zip->setEncryptionName($fileName, \ZipArchive::EM_AES_256);
            }
        }

        if (!$zip->close()) {
            throw new \RuntimeException(\sprintf('Failed to close zip file for writing: %s. Error: %s', $destination, $zip->getStatusString()));
        }
    }

    private function isZipBinaryAvailable(): bool
    {
        return 0 === $this->processRunner->exitCode(['which', 'zip'], context: new Context(quiet: true));
    }
}
