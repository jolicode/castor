<?php

namespace version_guard;

use Castor\Attribute\AsTask;

use function Castor\guard_min_version;

#[AsTask(description: 'Check if the minimum castor version requirement is met')]
function min_version_check(): void
{
    guard_min_version('v0.5.0');
}

#[AsTask(description: 'Check if the minimum castor version requirement is met (fail)')]
function min_version_check_fail(): void
{
    guard_min_version('v999.0.0');
}
