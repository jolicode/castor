#!/usr/bin/env php
<?php

$phar = new Phar(__DIR__ . '/../examples/run.phar');
$phar['index.php'] = '<?php array_shift($_SERVER["argv"]); var_dump($_SERVER["argv"]); exit(0);';
$phar->setDefaultStub('index.php', 'index.php');
$phar->stopBuffering();
