<?php

namespace Castor\CommandBuilder;

use Castor\Context;

interface ContextUpdaterInterface
{
    public function updateContext(Context $context): Context;
}
