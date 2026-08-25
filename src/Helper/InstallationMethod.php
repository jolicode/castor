<?php

namespace Castor\Helper;

enum InstallationMethod: string
{
    case Phar = 'phar';
    case Static = 'static';
    case ComposerGlobal = 'composer global';
    case Composer = 'composer';
    case Source = 'source';

    public function isSelfUpdateable(): bool
    {
        return match ($this) {
            self::Phar, self::Static, self::ComposerGlobal => true,
            self::Composer, self::Source => false,
        };
    }
}
