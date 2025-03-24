<?php

namespace archive;

use Castor\Attribute\AsTask;
use Castor\Helper\CompressionMethod;

use function Castor\io;
use function Castor\zip;
use function Castor\zip_binary;
use function Castor\zip_php;

const SAMPLE_FILE_BASE = __DIR__ . '/fixtures/archive/sample';
const SAMPLE_FILE = SAMPLE_FILE_BASE . '.txt';
const SAMPLE_FILE_ZIP = SAMPLE_FILE_BASE . '.zip';

const SAMPLE_DIR = __DIR__ . '/fixtures/archive/sample_dir';
const SAMPLE_DIR_ZIP = SAMPLE_DIR . '.zip';

#[AsTask(description: 'Compress files into a zip archive using native binary or fallback to ZipArchive php class', name: 'zip')]
function zip_archive(): void
{
    zip(SAMPLE_FILE, SAMPLE_FILE_ZIP, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('File ' . SAMPLE_FILE . ' compressed into ' . SAMPLE_FILE_ZIP);

    zip(SAMPLE_DIR, SAMPLE_DIR_ZIP, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('Directory ' . SAMPLE_DIR . ' compressed into ' . SAMPLE_DIR_ZIP);
}

#[AsTask(description: 'Compress files into a zip archive using native binary', name: 'zip-binary')]
function zip_binary_archive(): void
{
    zip_binary(SAMPLE_FILE, SAMPLE_FILE_ZIP, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('File ' . SAMPLE_FILE . ' compressed into ' . SAMPLE_FILE_ZIP);

    zip_binary(SAMPLE_DIR, SAMPLE_DIR_ZIP, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('Directory ' . SAMPLE_DIR . ' compressed into ' . SAMPLE_DIR_ZIP);
}

#[AsTask(description: 'Compress files into a zip archive using ZipArchive php class', name: 'zip-php')]
function zip_php_archive(): void
{
    zip_php(SAMPLE_FILE, SAMPLE_FILE_ZIP, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('File ' . SAMPLE_FILE . ' compressed into ' . SAMPLE_FILE_ZIP);

    zip_php(SAMPLE_DIR, SAMPLE_DIR_ZIP, 'secret', CompressionMethod::BZIP2, 9, overwrite: true);
    io()->success('Directory ' . SAMPLE_DIR . ' compressed into ' . SAMPLE_DIR_ZIP);
}
