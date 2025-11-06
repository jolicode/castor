<?php

namespace archive;

use Castor\Attribute\AsTask;
use Castor\Helper\CompressionMethod;

use function Castor\io;
use function Castor\zip;

#[AsTask(description: 'Compress files into a zip archive using native binary or fallback to ZipArchive php class')]
function zip_(): void
{
    $source = __FILE__;
    $destination = __DIR__ . '/archive.zip';
    zip($source, $destination, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('File ' . $source . ' compressed into ' . $destination);

    $source = __DIR__;
    $destination = __DIR__ . '/archive_dir.zip';
    zip("{$source}/.", $destination, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('Directory ' . $source . ' compressed into ' . $destination);
}
