<?php

namespace Castor\Exception;

use Castor\Helper\PathHelper;

class FunctionConfigurationException extends \InvalidArgumentException
{
    public function __construct(string $message, \ReflectionFunction|\ReflectionClass $function, ?\Throwable $e = null)
    {
        $message = \sprintf(<<<'TXT'
            Function "%s()" is not properly configured:
            %s
            Defined in "%s" line %d.
            TXT,
            $function->getName(),
            $message,
            PathHelper::makeRelative((string) $function->getFileName()),
            $function->getStartLine(),
        );

        parent::__construct($message, previous: $e);
    }
}
