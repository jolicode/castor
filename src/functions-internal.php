<?php

namespace Castor\Internal;

use Castor\FunctionFinder;

/**
 * Don't leak internal variables when requiring files.
 *
 * @internal
 */
function castor_require(string $file): void
{
    if (!is_file($file)) {
        throw new \RuntimeException(sprintf('Could not find file "%s".', $file));
    }

    FunctionFinder::$files[] = $file;

    require_once $file;
}

/**
 * Remove the last internal frames (like the call to run()) to display a nice message to the end user.
 *
 * @internal
 */
function fix_exception(\Exception $exception, int $depth = 0): \Exception
{
    $lastFrame = $exception->getTrace()[$depth];
    foreach (['file', 'line'] as $key) {
        if (!\array_key_exists($key, $lastFrame)) {
            continue;
        }
        $r = new \ReflectionProperty(\Exception::class, $key);
        $r->setValue($exception, $lastFrame[$key]);
    }

    return $exception;
}
