<?php

use Castor\Attribute\AsArgsAfterOptionEnd;
use Castor\Attribute\AsTask;

#[AsTask()]
function duplicate_args_after_option_end(#[AsArgsAfterOptionEnd] array $a = [], #[AsArgsAfterOptionEnd] array $b = []): void
{
}
