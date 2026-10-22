<?php

namespace Castor\Attribute;

/**
 * Fills the parameter of a task, an array, with the raw arguments and options
 * given after "--", to forward them to another command for instance. They are
 * not validated, unlike the ones before "--".
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class AsArgsAfterOptionEnd extends AsRawTokens
{
}
