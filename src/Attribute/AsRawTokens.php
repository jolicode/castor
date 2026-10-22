<?php

namespace Castor\Attribute;

/**
 * Fills the parameter of a task, an array, with the raw arguments and options
 * given to the task, to forward them to another command for instance. They are
 * not validated.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsRawTokens
{
}
