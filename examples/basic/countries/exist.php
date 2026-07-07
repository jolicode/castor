<?php

namespace countries;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\country_exists;
use function Castor\io;

#[AsTask(name: 'exists', description: 'Test if a country exists')]
function test_country_exists(#[AsArgument] string $country): void
{
    if (!country_exists($country)) {
        io()->error('The country "' . $country . '" does not exist.');

        return;
    }

    io()->success('The country "' . $country . '" does exist.');
}
