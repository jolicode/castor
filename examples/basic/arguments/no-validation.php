<?php

namespace arguments;

use Castor\Attribute\AsTask;

#[AsTask(ignoreValidationErrors: true)]
function no_validation(): void
{
}
