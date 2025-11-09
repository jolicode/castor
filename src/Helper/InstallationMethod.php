<?php

namespace Castor\Helper;

enum InstallationMethod: string
{
    case Phar = 'phar';
    case Static = 'static';
    case ComposerGlobal = 'composer global';
    case Composer = 'composer';
    case Source = 'source';
}
