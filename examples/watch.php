<?php

namespace watch;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\watch;

#[AsTask(description: 'Watches on filesystem changes')]
function fs_change(): void
{
    echo "Try editing a file\n";
    watch(\dirname(__DIR__) . '/...', function (string $name, string $type) {
        echo "File {$name} has been {$type}\n";
    });
}

#[AsTask(description: 'Watches on filesystem changes and stop after first change')]
function stop(): void
{
    watch(\dirname(__DIR__) . '/...', function (string $name, string $type) {
        echo "File {$name} has been {$type}\n";

        return false;
    });
    echo "Stop watching\n";
}

#[AsTask(description: 'Watches on filesystem changes with 2 watchers in parallel')]
function parallel_change(): void
{
    parallel(
        function () {
            for ($i = 1; $i <= 10; ++$i) {
                echo "[app] Writing hello-{$i}.txt\n";
                file_put_contents("hello-{$i}.txt", "Hello {$i}\n", \FILE_APPEND);
                echo "[app] Deleting hello-{$i}.txt\n";
                unlink("hello-{$i}.txt");
                if (\Fiber::getCurrent()) {
                    \Fiber::suspend();
                }
                usleep(500_000);
            }
        },
        function () {
            watch(\dirname(__DIR__) . '/...', function ($name, $type) {
                echo "[watcher:A] File {$name} has been {$type}\n";
            });
        },
        function () {
            watch(\dirname(__DIR__) . '/...', function ($name, $type) {
                echo "[watcher:B] Second : File {$name} has been {$type}\n";
            });
        },
    );
}
