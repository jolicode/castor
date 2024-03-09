<?php

use Castor\Attribute\AsContext;
use Castor\Context;

#[AsContext(default: true)]
function one(): Context
{
    return new Context();
}

#[AsContext(default: true)]
function two(): Context
{
    return new Context();
}
