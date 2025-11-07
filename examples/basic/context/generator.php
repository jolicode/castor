<?php

namespace context;

use Castor\Attribute\AsContextGenerator;
use Castor\Context;

/**
 * @return iterable<string, \Closure(): Context>
 */
#[AsContextGenerator()]
function create_context_generator(): iterable
{
    yield 'dynamic' => fn () => new Context([
        'name' => 'dynamic',
        'production' => false,
        'foo' => 'baz',
    ]);
}
