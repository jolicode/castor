<?php

namespace Castor\Attribute;

/**
 * Turns the function into several contexts: it takes no argument, and returns
 * closures returning a Context, keyed by context name.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsContextGenerator
{
}
