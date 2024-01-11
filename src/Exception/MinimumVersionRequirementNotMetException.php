<?php

namespace Castor\Exception;

class MinimumVersionRequirementNotMetException extends \RuntimeException
{
    public function __construct(
        readonly string $requiredVersion,
        readonly string $currentVersion,
    ) {
        parent::__construct("Castor requires at least version {$requiredVersion}, you are using {$currentVersion}. Please consider upgrading.");
    }
}
