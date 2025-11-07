<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Context;

#[AsContext(default: true, name: 'my_default')]
function create_default_context(): Context
{
    return new Context([
        'name' => 'my_default',
        'production' => false,
        'foo' => 'bar',
        'nested' => [
            'merge' => [
                'key' => [
                    'value' => 'should keep this',
                    'replaced' => 'should be replaced',
                ],
                'another' => 'should keep',
            ],
            'another' => 'should keep',
        ],
    ]);
}
