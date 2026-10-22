<?php

namespace Castor\Attribute;

/**
 * Like AsArgument, with the shell completion suggesting paths.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsPathArgument extends AsArgument
{
    /**
     * @param ?string                   $directory The directory to suggest paths from, relative to the project root. Defaults to the project root
     * @param string|array<string>|null $filter    The patterns of the file names to suggest, like "*.php". Directories are always suggested
     */
    public function __construct(
        ?string $name = null,
        string $description = '',
        public readonly ?string $directory = null,
        public readonly string|array|null $filter = null,
    ) {
        parent::__construct($name, $description);
    }
}
