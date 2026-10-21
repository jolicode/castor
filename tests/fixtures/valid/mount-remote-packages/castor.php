<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use function Castor\mount;

mount(__DIR__ . '/mounted');
