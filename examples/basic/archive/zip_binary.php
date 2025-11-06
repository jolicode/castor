<?php

namespace archive;

use Castor\Attribute\AsTask;
use Castor\Helper\CompressionMethod;

use function Castor\io;
use function Castor\zip_binary;

#[AsTask(description: 'Compress files into a zip archive using native binary')]
function zip_binary_(): void
{
    $source = __FILE__;
    $destination = __DIR__ . '/archive_binary.zip';
    zip_binary($source, $destination, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('File ' . $source . ' compressed into ' . $destination);

    $source = __DIR__;
    $destination = __DIR__ . '/archive_binary_dir.zip';
    zip_binary("{$source}/.", $destination, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('Directory ' . $source . ' compressed into ' . $destination);
}
