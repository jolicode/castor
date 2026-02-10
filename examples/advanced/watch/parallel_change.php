<?php

namespace watch;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\parallel;
use function Castor\watch;

#[AsTask(description: 'Watches on filesystem changes with 2 watchers in parallel')]
function parallel_change(): void
{
    parallel(
        static function () {
            for ($i = 1; $i <= 10; ++$i) {
                io()->writeln("[app] Writing hello-{$i}.txt");
                file_put_contents("hello-{$i}.txt", "Hello {$i}\n", \FILE_APPEND);
                io()->writeln("[app] Deleting hello-{$i}.txt");
                unlink("hello-{$i}.txt");
                if (\Fiber::getCurrent()) {
                    \Fiber::suspend();
                }
                usleep(500_000);
            }
        },
        static function () {
            watch(\dirname(__DIR__) . '/...', static function ($name, $type) {
                io()->writeln("[watcher:A] File {$name} has been {$type}");
            });
        },
        static function () {
            watch(\dirname(__DIR__) . '/...', static function ($name, $type) {
                io()->writeln("[watcher:B] Second : File {$name} has been {$type}");
            });
        },
    );
}
