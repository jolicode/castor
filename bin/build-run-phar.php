#!/usr/bin/env php
<?php

/**
 * This file is used to build the 'examples/run.phar' file to test the 'run_phar' function.
 */
$phar = new Phar(__DIR__ . '/../examples/run.phar');
$phar['index.php'] = '<?php array_shift($_SERVER["argv"]); var_dump($_SERVER["argv"]); var_dump(ini_get("memory_limit")); exit(0);';
$phar->setDefaultStub('index.php', 'index.php');
$phar->stopBuffering();
