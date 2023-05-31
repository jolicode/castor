#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Castor\Console\ApplicationFactory;
use Castor\Tests\OutputCleaner;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

use function Symfony\Component\String\u;

$application = ApplicationFactory::create(new NullLogger());
$application->setAutoExit(false);
$application
    ->run(new ArrayInput(['command' => 'list', '--format' => 'json']), $o = new BufferedOutput())
;
$applicationDescription = json_decode($o->fetch(), true);

$commandFilterList = [
    '_complete',
    'completion',
    'help',
    // Never complete
    'watch:fs-change',
    'watch:parallel-change',
    'watch:stop',
    // Not examples
    'watcher:build',
    'watcher:linux',
    'watcher:macos',
    'watcher:windows',
    // Customized tests
    'cd:directory',
    'log:all-level',
    'log:error',
    'log:info',
    'log:with-context',
    'parallel:sleep',
    'run:run-parallel',
    'run:run',
];
$optionFilterList = array_flip(['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'context']);
foreach ($applicationDescription['commands'] as $command) {
    if (in_array($command['name'], $commandFilterList, true)) {
        continue;
    }

    echo "Generating test for {$command['name']}\n";

    $args = [
        $command['name'],
    ];

    $options = array_diff_key($command['definition']['options'], $optionFilterList);
    foreach ($command['definition']['arguments'] as $argument) {
        if (!$argument['is_required']) {
            continue;
        }
        if (isset($argument['default'])) {
            continue;
        }
        $args[] = sprintf('FIXME(%s)', $argument['name']);
    }
    foreach ($options as $option) {
        $args[] = $option['name'];
        if (!$option['accept_value']) {
            continue;
        }
        $args[] = $option['default'] ?? 'FIXME';
    }

    $class = u($command['name'])->camel()->title()->append('Test')->toString();
    $task = $command['name'];
    $methodName = u($command['name'])->replace(':', '_')->replace('-', '_')->camel()->title()->toString();

    add_test($args, $class, $task, $methodName);
}

add_test(['context:context', '--context', 'exec'], 'ContextContextExec', 'context:context');
add_test(['context:context', '--context', 'my_default', '-vvv'], 'ContextContextMyDefault', 'context:context');
add_test(['context:context', '--context', 'no_no_exist'], 'ContextContextDoNotExist', 'context:context');
add_test(['context:context', '--context', 'production'], 'ContextContextProduction', 'context:context');
add_test(['parallel:sleep', '--sleep5', '0', '--sleep7', '0', '--sleep10', '0'], 'ParallelSleepTest');

function add_test(array $args, string $class)
{
    $fp = fopen(__FILE__, 'r');
    fseek($fp, __COMPILER_HALT_OFFSET__ + 1);
    $template = stream_get_contents($fp);

    $process = new Process(
        [\PHP_BINARY, 'bin/castor', ...$args],
        cwd: __DIR__ . '/../',
        env: [
            'COLUMNS' => 120,
        ],
    );
    $process->run();

    $code = strtr($template, [
        '{{ class_name }}' => $class,
        '{{ task }}' => $args[0],
        '{{ args }}' => implode(', ', array_map(fn ($arg) => var_export($arg, true), $args)),
        '{{ exitCode }}' => $process->getExitCode(),
    ]);

    file_put_contents(__DIR__ . '/../tests/Examples/' . $class . '.php', $code);
    file_put_contents(__DIR__ . '/../tests/Examples/' . $class . '.php.output.txt', OutputCleaner::cleanOutput($process->getOutput()));
    $err = OutputCleaner::cleanOutput($process->getErrorOutput());
    if ($err) {
        file_put_contents(__DIR__ . '/../tests/Examples/' . $class . '.php.err.txt', $process->getErrorOutput());
    }
}

__halt_compiler();
<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class {{ class_name }} extends TaskTestCase
{
    // {{ task }}
    public function test(): void
    {
        $process = $this->runTask([{{ args }}]);

        $this->assertSame({{ exitCode }}, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
