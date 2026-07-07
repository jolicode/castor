<?php

namespace currencies;

use Castor\Attribute\AsTask;

use function Castor\currency_codes;
use function Castor\io;

#[AsTask(name: 'choice', description: 'Choice with currencies')]
function currency_choice(): void
{
    io()->choice('Choose a currency', choices: currency_codes(), default: 'EUR');
}
