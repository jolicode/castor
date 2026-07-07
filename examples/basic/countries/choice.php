<?php

namespace countries;

use Castor\Attribute\AsTask;

use function Castor\country_codes;
use function Castor\io;

#[AsTask(name: 'choice', description: 'Choice with countries')]
function country_choice(): void
{
    io()->choice('Choose a country', choices: country_codes(), default: 'FR');
}
