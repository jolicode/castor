<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Context;

#[AsContext(name: 'production')]
function create_production_context(): Context
{
    return create_default_context()
        ->withData(
            [
                'name' => 'production',
                'production' => true,
                'nested' => [
                    'merge' => [
                        'key' => [
                            'replaced' => 'replaced value',
                            'new' => 'new value',
                        ],
                    ],
                ],
            ],
            recursive: true
        )
    ;
}
