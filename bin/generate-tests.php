#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Castor\Console\ApplicationFactory;
use Castor\Tests\OutputCleaner;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

use function Symfony\Component\String\u;

$application = ApplicationFactory::create();
$application->setAutoExit(false);
$application
    ->run(new ArrayInput(['command' => 'list', '--format' => 'json']), $o = new BufferedOutput())
;
$applicationDescription = json_decode($o->fetch(), true);

$commandFilterList = [
    '_complete',
    'completion',
    'help',
    // Never complete or impossible to run
    'watch:fs-change',
    'watch:parallel-change',
    'watch:stop',
    'ssh:ls',
    // Not examples
    'castor:phar:build',
    'castor:phar:darwin',
    'castor:phar:linux',
    'castor:phar:windows',
    'castor:watcher:build',
    'castor:watcher:darwin',
    'castor:watcher:linux',
    'castor:watcher:windows',
    'castor:qa:cs',
    'castor:qa:phpstan',
    // Customized tests
    'cd:directory',
    'log:all-level',
    'log:error',
    'log:info',
    'log:with-context',
    'parallel:sleep',
    'repack',
    'run:ls',
    'run:run-parallel',
    'fingerprint:task-with-some-fingerprint', // Tested in Castor\Tests\Fingerprint\FingerprintTaskWithSomeFingerprintTest
    'fingerprint:task-with-some-fingerprint-with-helper', // Tested in Castor\Tests\Fingerprint\FingerprintTaskWithSomeFingerprintWithHelperTest
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

    add_test($args, $class);
}

add_test(['parallel:sleep', '--sleep5', '0', '--sleep7', '0', '--sleep10', '0'], 'ParallelSleepTest');
add_test(['context:context', '--context', 'run'], 'ContextContextRunTest');
add_test(['context:context', '--context', 'my_default', '-vvv'], 'ContextContextMyDefaultTest');
add_test(['context:context', '--context', 'no_no_exist'], 'ContextContextDoNotExistTest');
add_test(['context:context', '--context', 'production'], 'ContextContextProductionTest');
add_test(['context:context', '--context', 'path'], 'ContextContextPathTest');
add_test(['context:context', '--context', 'dynamic'], 'ContextContextDynamicTest');
add_test(['enabled:hello', '--context', 'production'], 'EnabledInProduction');
add_test([], 'NewProjectTest', '/tmp');
add_test(['init'], 'NewProjectInitTest', '/tmp');
add_test(['unknown:command'], 'NoConfigUnknownTest', '/tmp');
add_test(['unknown:command', 'toto', '--foo', 1], 'NoConfigUnknownWithArgsTest', '/tmp');
add_test(['completion', 'bash'], 'NoConfigCompletionTest', '/tmp');

function add_test(array $args, string $class, string $cwd = null)
{
    $fp = fopen(__FILE__, 'r');
    fseek($fp, __COMPILER_HALT_OFFSET__ + 1);
    $template = stream_get_contents($fp);

    $process = new Process(
        [\PHP_BINARY,  __DIR__ . '/castor', ...$args],
        cwd: $cwd ?: __DIR__ . '/../',
        env: [
            'COLUMNS' => 120,
        ],
    );
    $process->run();

    $code = strtr($template, [
        '{{ class_name }}' => $class,
        '{{ task }}' => $args[0] ?? 'no task',
        '{{ args }}' => implode(', ', array_map(fn ($arg) => var_export($arg, true), $args)),
        '{{ exitCode }}' => $process->getExitCode(),
        '{{ cwd }}' => $cwd ? ', ' . var_export($cwd, true) : '',
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
        $process = $this->runTask([{{ args }}]{{ cwd }});

        $this->assertSame({{ exitCode }}, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
