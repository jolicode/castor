<?php

namespace arguments;

use Castor\Attribute\AsPathOption;
use Castor\Attribute\AsTask;

#[AsTask(description: 'Provides autocomplete for .yaml files in the config directory')]
function path_option_with_filter(
    #[AsPathOption(description: 'Configuration file', directory: 'config', filter: '*.yaml')]
    ?string $file = null,
): void {
}
