<?php

namespace remote_import;

use Castor\Attribute\AsTask;

use function Castor\import;

// Importing tasks from a Composer package
import('composer://pyrech/castor-example');
import('composer://pyrech/castor-example', file: 'foobar.php');
// Importing tasks from a Composer package not published on packagist (but still having a composer.json)
import('composer://pyrech/castor-example-package-not-published');
// Importing tasks from a repository not using Composer
import('composer://pyrech/foobar');

#[AsTask(description: 'Use functions imported from remote packages')]
function remote_tasks(): void
{
    \pyrech\helloExample(); // from composer://pyrech/castor-example
    \pyrech\foobar(); // from package://pyrech/foobar
}
