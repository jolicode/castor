<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsContext;
use Castor\Context;

#[AsContext()]
function one(): Context
{
    return new Context();
}

#[AsContext()]
function two(): Context
{
    return new Context();
}
