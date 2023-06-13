<?php

namespace filesystem;

use Castor\Attribute\AsTask;
use Symfony\Component\Filesystem\Path;

use function Castor\finder;
use function Castor\fs;

#[AsTask(description: 'Performs some operations on the filesystem')]
function filesystem(): void
{
    $fs = fs();

    $dir = '/tmp/foo';

    echo $dir, ' directory exist: ', $fs->exists($dir) ? 'yes' : 'no', \PHP_EOL;

    $fs->mkdir($dir);
    $fs->touch($dir . '/bar.md');

    echo $dir, ' is an absolute path: ', Path::isAbsolute($dir) ? 'yes' : 'no', \PHP_EOL;
    echo '../ is an absolute path: ', Path::isAbsolute('../') ? 'yes' : 'no', \PHP_EOL;

    $fs->remove($dir);

    echo 'Absolute path: ', Path::makeAbsolute('../', $dir), \PHP_EOL;
}

#[AsTask(description: 'Search files and directories on the filesystem')]
function find(): void
{
    $finder = finder();

    echo 'Number of PHP files: ', $finder->name('*.php')->in(__DIR__)->count(), \PHP_EOL;
}
