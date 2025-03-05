<?php

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\import;
use function Castor\io;
use function Castor\mount;
use function Castor\run;
use function Castor\with;

import(__DIR__ . '/examples');
import(__DIR__ . '/tools/docker/castor.php');
import(__DIR__ . '/tools/markdown/castor.php');
import(__DIR__ . '/.castor/qa.php');
import(__DIR__ . '/tools/mkdocs/castor.php');
import(__DIR__ . '/tools/static/castor.php');

mount(__DIR__ . '/tools/phar');
mount(__DIR__ . '/tools/release');
mount(__DIR__ . '/tools/watcher');

#[AsTask(description: 'Update all dependencies')]
function update(): void
{
    io()->title('Update all dependencies');

    with(\castor\phar\update(...), context: context()->withWorkingDirectory(__DIR__ . '/tools/phar'));
    \qa\cs\update();
    \qa\phpstan\update();

    io()->section('Update castor dependencies');
    run(['composer', 'update']);
}
