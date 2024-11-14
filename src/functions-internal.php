<?php

namespace Castor\Internal;

/**
 * Don't leak internal variables when requiring files.
 *
 * Must only be called by Castor\Import\Importer::importFile()
 *
 * @internal
 */
function castor_require(string $file): void
{
    if (!is_file($file)) {
        throw new \RuntimeException(\sprintf('Could not find file "%s".', $file));
    }

    require_once $file;
}
