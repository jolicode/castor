<?php

namespace locales;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\locale_codes;

#[AsTask(name: 'choice', description: 'Choice with locales')]
function locale_choice(): void
{
    io()->choice('Choose a locale', choices: locale_codes(), default: 'fr_FR');
}
