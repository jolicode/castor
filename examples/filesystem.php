<?php

namespace filesystem;

use Castor\Attribute\AsTask;
use Symfony\Component\Filesystem\Path;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

#[AsTask(description: 'Performs some operations on the filesystem')]
function filesystem(): void
{
    $fs = fs();

    $dir = '/tmp/foo';

    io()->writeln($dir . ' directory exist: ' . ($fs->exists($dir) ? 'yes' : 'no'));

    $fs->mkdir($dir);
    $fs->touch($dir . '/bar.md');

    io()->writeln($dir . ' is an absolute path: ' . (Path::isAbsolute($dir) ? 'yes' : 'no'));
    io()->writeln('../ is an absolute path: ' . (Path::isAbsolute('../') ? 'yes' : 'no'));

    $fs->remove($dir);

    io()->writeln('Absolute path: ' . Path::makeAbsolute('../', $dir));
}

#[AsTask(description: 'Search files and directories on the filesystem')]
function find(): void
{
    $finder = finder();

    io()->writeln('Number of PHP files: ' . $finder->name('*.php')->in(__DIR__)->count());
}
