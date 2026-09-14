<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use function Castor\import;

import('composer://pyrech/castor-example');
import('composer://pyrech/castor-example', file: 'castor.php');
