<?php

namespace remote_import;

use Castor\Attribute\AsTask;

use function Castor\import;

// Importing tasks from a Composer package
import('composer://pyrech/castor-example', version: '^1.0');
// Importing tasks from a Composer package not published on packagist (but still having a composer.json)
import('composer://pyrech/castor-example-package-not-published', version: '*', vcs: 'https://github.com/pyrech/castor-example-package-not-published.git');
// Importing tasks from a repository not using Composer
import('package://pyrech/foobar', source: [
    'url' => 'https://github.com/pyrech/castor-example-misc.git',
    'type' => 'git',
    'reference' => 'main', //  commit id, branch or tag name
]);

#[AsTask(description: 'Use functions imported from remote packages')]
function remote_tasks(): void
{
    \pyrech\helloExample(); // from composer://pyrech/castor-example
    \pyrech\foobar(); // from package://pyrech/foobar
}
