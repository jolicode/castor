<?php

namespace archive;

use Castor\Attribute\AsTask;
use Castor\Helper\CompressionMethod;

use function Castor\io;
use function Castor\zip_php;

#[AsTask(description: 'Compress files into a zip archive using ZipArchive php class')]
function zip_php_(): void
{
    $source = __FILE__;
    $destination = __DIR__ . '/archive_php.zip';
    zip_php($source, $destination, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('File ' . $source . ' compressed into ' . $destination);

    $source = __DIR__;
    $destination = __DIR__ . '/archive_php_dir.zip';
    zip_php("{$source}/.", $destination, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('Directory ' . $source . ' compressed into ' . $destination);
}
