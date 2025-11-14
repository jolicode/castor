<?php

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\fs;
use function Castor\io;

#[AsTask(description: 'Demonstrates context-aware filesystem operations')]
function contextAware(): void
{
    $fs = fs();

    // Create a temporary directory for the test
    $baseDir = '/tmp/castor-fs-context-test';
    $fs->remove($baseDir);
    $fs->mkdir($baseDir);

    // Create a subdirectory
    $fs->mkdir($baseDir . '/subdir');

    // Test 1: Using fs() with default context (current working directory)
    io()->section('Test 1: Default context');
    io()->writeln('Current working directory: ' . context()->workingDirectory);

    // Create a file using an absolute path - should work regardless of context
    $fs->dumpFile($baseDir . '/absolute-path.txt', 'Created with absolute path');
    io()->writeln('Created file with absolute path: ' . $baseDir . '/absolute-path.txt');
    io()->writeln('File exists: ' . ($fs->exists($baseDir . '/absolute-path.txt') ? 'yes' : 'no'));

    // Test 2: Using fs() with a different working directory context
    io()->section('Test 2: Context with different working directory');
    $customContext = context()->withWorkingDirectory($baseDir . '/subdir');
    $contextualFs = fs($customContext);

    io()->writeln('Context working directory: ' . $customContext->workingDirectory);

    // Create a file using a relative path - should be created in context's working directory
    $contextualFs->dumpFile('relative-path.txt', 'Created with relative path in context');
    io()->writeln('Created file with relative path: relative-path.txt');

    // Check if the file was created in the correct location
    $expectedPath = $baseDir . '/subdir/relative-path.txt';
    io()->writeln('Actual file location: ' . $expectedPath);
    io()->writeln('File exists at expected location: ' . ($fs->exists($expectedPath) ? 'yes' : 'no'));

    // Verify content
    if ($fs->exists($expectedPath)) {
        $content = file_get_contents($expectedPath);
        io()->writeln('File content: ' . $content);
    }

    // Test 3: Demonstrating that absolute paths work the same way in any context
    io()->section('Test 3: Absolute paths are context-independent');
    $contextualFs->dumpFile($baseDir . '/another-absolute.txt', 'Created with absolute path from contextualized fs');
    io()->writeln('Created file with absolute path from contextualized fs');
    io()->writeln('File exists: ' . ($fs->exists($baseDir . '/another-absolute.txt') ? 'yes' : 'no'));

    // Cleanup
    $fs->remove($baseDir);
    io()->success('Context-aware filesystem test completed successfully!');
}
