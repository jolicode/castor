<?php

namespace Castor\Helper;

enum CompressionMethod: string
{
    case STORE = 'store';
    case BZIP2 = 'bzip2';
    case ZSTD = 'zstd';
    case DEFLATE = 'deflate';

    public function toZipArchiveMethod(): int
    {
        return match ($this) {
            self::BZIP2 => \ZipArchive::CM_BZIP2,
            self::STORE => \ZipArchive::CM_STORE,
            self::DEFLATE => \ZipArchive::CM_DEFLATE,
            self::ZSTD => \ZipArchive::CM_ZSTD,
        };
    }
}
