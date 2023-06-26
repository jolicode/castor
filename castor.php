<?php

use Castor\Attribute\AsTask;

use function Castor\import;

import(__DIR__ . '/examples');
import(__DIR__ . '/tools/phar/castor.php');
import(__DIR__ . '/tools/watcher/castor.php');
import(__DIR__ . '/tools/php-cs-fixer/castor.php');
import(__DIR__ . '/tools/phpstan/castor.php');

#[AsTask(description: 'hello')]
function hello(): void
{
    echo 'Hello world!';
}
