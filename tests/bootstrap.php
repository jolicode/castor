<?php

require __DIR__ . '/../vendor/autoload.php';

putenv('COMPOSER_VENDOR_DIR');
unset($_SERVER['COMPOSER_VENDOR_DIR'], $_ENV['COMPOSER_VENDOR_DIR']);
