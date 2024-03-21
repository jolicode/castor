<?php

namespace Castor\Exception;

class MinimumVersionRequirementNotMetException extends \RuntimeException
{
    public function __construct(
        readonly string $requiredVersion,
        readonly string $currentVersion,
    ) {
        parent::__construct("This project requires Castor in version {$requiredVersion} or greater, you are using {$currentVersion}. Please consider upgrading.");
    }
}
