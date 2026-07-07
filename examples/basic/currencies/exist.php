<?php

namespace currencies;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\currency_exists;
use function Castor\io;

#[AsTask(name: 'exists', description: 'Test if a currency exists')]
function test_currency_exists(#[AsArgument] string $currency): void
{
    if (!currency_exists($currency)) {
        io()->error('The currency "' . $currency . '" does not exist.');

        return;
    }

    io()->success('The currency "' . $currency . '" does exist.');
}
