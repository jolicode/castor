<?php

namespace locales;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\locale_exists;

#[AsTask(name: 'exists', description: 'Test if a locale exists')]
function test_locale_exists(#[AsArgument] string $locale): void
{
    if (!locale_exists($locale)) {
        io()->error('The locale "' . $locale . '" does not exist.');

        return;
    }

    io()->success('The locale "' . $locale . '" does exist.');
}
