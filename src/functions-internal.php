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

function clone($object, $args) {
    if (PHP_VERSION_ID >= 80500) {
        return \clone($object, ...$args);
    }

    $cloned = clone $object;
    // use reflection to clone
    $reflection = new \ReflectionClass($object);

    foreach ($args as $name => $value) {
        $prop = $reflection->getProperty($name);
        $prop->setRawValue($cloned, $value);
    }

    return $cloned;
}
