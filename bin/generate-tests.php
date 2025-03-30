#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Castor\Console\ApplicationFactory;
use Castor\Tests\Helper\OutputCleaner;
use Castor\Tests\Helper\WebServerHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

use function Symfony\Component\String\u;

$_SERVER['ENDPOINT'] ??= 'http://127.0.0.1:9955';
$_SERVER['CASTOR_CACHE_DIR'] ??= '/tmp/castor-tests/cache';
if (!$_SERVER['CASTOR_CACHE_DIR']) {
    throw new RuntimeException('CASTOR_CACHE_DIR is not set or empty.');
}

WebServerHelper::start();

displayTitle('Cleaning');

$fs = new Filesystem();
$fs->remove($_SERVER['CASTOR_CACHE_DIR']);
$fs->remove(__DIR__ . '/../tests/Generated');
$fs->mkdir(__DIR__ . '/../tests/Generated');
$fs->remove((new Finder())
    ->in(__DIR__ . '/../tests/fixtures')
    ->in(__DIR__ . '/../tests/fixtures')
    ->path('composer.installed')
    ->ignoreDotFiles(false)
);
echo "\nDone.\n";

displayTitle('Retrieving example tasks');

$application = ApplicationFactory::create();
$application->setAutoExit(false);
$application
    ->run(new ArrayInput(['command' => 'list', '--format' => 'json']), $o = new BufferedOutput())
;
$json = $o->fetch();

try {
    $applicationDescription = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    throw new RuntimeException('Could not get the list of commands. You probably break something:' . $json, previous: $e);
}

echo "\nDone.\n";

displayTitle('Generating tests for example tasks');

$taskFilterList = [
    '_complete',
    'completion',
    'help',
    // Never complete or impossible to run
    'castor:debug',
    'open:documentation',
    'open:multiple',
    'watch:fs-change',
    'watch:parallel-change',
    'watch:stop',
    // Not examples
    'castor:compile',
    'castor:phar:build',
    'castor:phar:darwin',
    'castor:phar:install',
    'castor:phar:linux',
    'castor:phar:update',
    'castor:phar:windows',
    'castor:release:release',
    'castor:repack',
    'castor:static:darwin-amd64',
    'castor:static:darwin-arm64',
    'castor:static:linux',
    'castor:watcher:build',
    'castor:watcher:darwin',
    'castor:watcher:linux',
    'castor:watcher:windows',
    'qa:cs:cs',
    'qa:cs:install',
    'qa:cs:update',
    'qa:phpstan:install',
    'qa:phpstan:phpstan',
    'qa:phpstan:update',
    'update',
    // Customized tests
    'archive:zip',
    'archive:zip-binary',
    'archive:zip-php',
    'crypto:decrypt',
    'crypto:encrypt',
    'crypto:decrypt-file',
    'crypto:encrypt-file',
    'fingerprint:task-with-a-fingerprint-and-force',
    'fingerprint:task-with-a-fingerprint-global',
    'fingerprint:task-with-a-fingerprint',
    'fingerprint:task-with-complete-fingerprint-check',
    'list',
    'log:all-level',
    'log:error',
    'log:info',
    'log:with-context',
    'parallel:sleep',
    'remote-import:remote-task-class',
    'remote-import:remote-tasks',
    'run:ls',
    'run:run-parallel',
    'symfony:greet',
    'symfony:hello',
    // Imported tasks
    'pyrech:hello-example',
    'pyrech:foobar',
];
$optionFilterList = array_flip(['help', 'quiet', 'verbose', 'silent', 'version', 'ansi', 'no-ansi', 'no-interaction', 'context', 'no-remote', 'update-remotes']);

foreach ($applicationDescription['commands'] as $task) {
    if (in_array($task['name'], $taskFilterList, true)) {
        continue;
    }

    echo "Generating test for {$task['name']}\n";

    $args = [
        $task['name'],
    ];

    $options = array_diff_key($task['definition']['options'], $optionFilterList);
    foreach ($task['definition']['arguments'] as $argument) {
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

    $class = u($task['name'])->camel()->title()->toString();

    add_test($args, $class);
}

echo "\nDone.\n";

displayTitle('Generating tests for broken fixtures');

$dirs = (new Finder())
    ->in($basePath = __DIR__ . '/../tests/fixtures/broken')
    ->depth(1)
;

foreach ($dirs as $dir) {
    $class = u($dir->getRelativePath())->camel()->title()->toString();
    add_test([], $class, '{{ base }}/tests/fixtures/broken/' . $dir->getRelativePath(), true);
}

echo "\nDone.\n";

displayTitle('Generating tests for valid fixtures');

add_test(['list'], 'LayoutWithFolder', '{{ base }}/tests/fixtures/valid/layout-with-folder');
add_test(['list'], 'LayoutWithOldFolder', '{{ base }}/tests/fixtures/valid/layout-with-old-folder');
add_test([], 'ImportSamePackageWithDefaultVersion', '{{ base }}/tests/fixtures/valid/import-same-package-with-default-version', needRemote: true, needResetVendor: true);
add_test(['fs-watch'], 'WatchWithForcedTimeout', '{{ base }}/tests/fixtures/valid/watch-with-forced-timeout');
add_test([], 'DefaultTask', '{{ base }}/tests/fixtures/valid/default-task');

echo "\nDone.\n";

displayTitle('Generating additional tests');

add_test(['args:passthru', 'a', 'b', '--no', '--foo', 'bar', '-x'], 'ArgPassthruExpanded');
add_test(['context:context', '--context', 'dynamic'], 'ContextContextDynamic');
add_test(['context:context', '--context', 'my_default', '-v'], 'ContextContextMyDefault');
add_test(['context:context', '--context', 'no_no_exist'], 'ContextContextDoNotExist');
add_test(['context:context', '--context', 'path'], 'ContextContextPath');
add_test(['context:context', '--context', 'production'], 'ContextContextProduction');
add_test(['context:context', '--context', 'run'], 'ContextContextRun');
add_test(['context:context', '--context', 'updated'], 'ContextContextUpdated');
add_test(['enabled:hello', '--context', 'production'], 'EnabledInProduction');
add_test(['failure:verbose-arguments'], 'FailureVerboseArgumentsTrue', input: "yes\n");
add_test(['list', '--raw', '--format', 'txt', '--short'], 'List', needRemote: true, skipOnBinary: true);
// Transient test, disabled for now
// add_test(['parallel:sleep', '--sleep5', '0', '--sleep7', '0', '--sleep10', '0'], 'ParallelSleep');
add_test(['run:exception', '-v'], 'RunExceptionVerbose');
add_test(['symfony:greet', 'World', '--french', 'COUCOU', '--punctuation', '!'], 'SymfonyGreet', skipOnBinary: true);
add_test(['symfony:hello'], 'SymfonyHello', skipOnBinary: true);
// In /tmp
add_test(['completion', 'bash'], 'NoConfigCompletion', '/tmp');
add_test(['init'], 'NewProjectInit', '/tmp');
add_test(['unknown:task', 'toto', '--foo', 1], 'NoConfigUnknownWithArgs', '/tmp');
add_test(['unknown:task'], 'NoConfigUnknown', '/tmp');
add_test([], 'NewProject', '/tmp');
// remote special test
add_test(['remote-import:remote-task-class'], 'RemoteImportClassWithVendorReset', needRemote: true, needResetVendor: true);

echo "\nDone.\n";

function add_test(array $args, string $class, ?string $cwd = null, bool $needRemote = false, bool $skipOnBinary = false, bool $needResetVendor = false, bool $needResetCache = true, ?string $input = null)
{
    $class .= 'Test';
    $fp = fopen(__FILE__, 'r');
    fseek($fp, __COMPILER_HALT_OFFSET__ + 1);
    $template = stream_get_contents($fp);

    $workingDirectory = $cwd ? str_replace('{{ base }}', __DIR__ . '/..', $cwd) : __DIR__ . '/..';

    $fs = new Filesystem();
    if ($needResetVendor) {
        $fs->remove($workingDirectory . '/.castor/vendor');
    }
    if ($needResetCache) {
        $fs->remove($_SERVER['CASTOR_CACHE_DIR']);
    }

    $inputStream = $input ? new InputStream() : null;
    $process = new Process(
        [\PHP_BINARY,  __DIR__ . '/castor', '--no-ansi', ...$args],
        cwd: $workingDirectory,
        env: [
            'COLUMNS' => 1000,
            'ENDPOINT' => $_SERVER['ENDPOINT'],
            'CASTOR_NO_REMOTE' => $needRemote ? 0 : 1,
            'CASTOR_TEST' => 'true',
            'CASTOR_CACHE_DIR' => $_SERVER['CASTOR_CACHE_DIR'],
            'XDEBUG_MODE' => 'off',
        ],
        input: $inputStream,
        timeout: null,
    );
    if ($inputStream) {
        $process->start();
        usleep(500_000);
        $inputStream->write($input);
        $inputStream->close();
        $process->wait();
    } else {
        $process->run();
    }

    $err = OutputCleaner::cleanOutput($process->getErrorOutput());

    $code = strtr($template, [
        '{{ class_name }}' => $class,
        '{{ task }}' => $args[0] ?? 'no task',
        '{{ args }}' => implode(', ', array_map(fn ($arg) => var_export($arg, true), $args)),
        '{{ exitCode }}' => $process->getExitCode(),
        '{{ cwd }}' => $cwd ? ', ' . var_export($cwd, true) : '',
        '{{ needRemote }}' => $needRemote ? ', needRemote: true' : '',
        '{{ needResetVendor }}' => $needResetVendor ? ', needResetVendor: true' : '',
        '{{ needResetCache }}' => $needResetCache ? '' : ', needResetCache: false',
        '{{ input }}' => $input ? ', input: ' . var_export($input, true) : '',
        '{{ skip-on-binary }}' => match ($skipOnBinary) {
            true => <<<'PHP'

                        if (self::$binary) {
                            $this->markTestSkipped('This test is not compatible with the binary version of Castor.');
                        }

                PHP,
            default => '',
        },
        '{{ error-assertion }}' => match ((bool) $err) {
            true => <<<'PHP'
                $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
                PHP,
            default => <<<'PHP'
                $this->assertSame('', $process->getErrorOutput());
                PHP,
        },
    ]);

    file_put_contents(__DIR__ . '/../tests/Generated/' . $class . '.php', $code);
    file_put_contents(__DIR__ . '/../tests/Generated/' . $class . '.php.output.txt', OutputCleaner::cleanOutput($process->getOutput()));
    if ($err) {
        file_put_contents(__DIR__ . '/../tests/Generated/' . $class . '.php.err.txt', $err);
    }
}

function displayTitle(string $title)
{
    echo "\n-- {$title} --\n\n";
}

__halt_compiler();
<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class {{ class_name }} extends TaskTestCase
{
    // {{ task }}
    public function test(): void
    {{{ skip-on-binary }}
        $process = $this->runTask([{{ args }}]{{ cwd }}{{ needRemote }}{{ needResetVendor }}{{ needResetCache }}{{ input }});

        if ({{ exitCode }} !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        {{ error-assertion }}
    }
}
