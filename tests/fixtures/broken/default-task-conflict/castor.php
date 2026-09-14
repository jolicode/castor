<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

#[AsTask(default: true)]
function about(): void
{
}

#[AsTask(default: true)]
function about2(): void
{
}
