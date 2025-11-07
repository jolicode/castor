<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\load_dot_env;

#[AsContext(name: 'dotenv')]
function create_context_from_dot_env(): Context
{
    /** @var array{name: string, production: bool} $data */
    $data = load_dot_env(__DIR__ . '/.env');

    return new Context($data);
}
