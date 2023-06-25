<?php

namespace Castor\Remote;

enum TrustEnum: string
{
    case NOT_NOW = 'not now';
    case NEVER = 'never';
    case ONLY_THIS_TIME = 'only this time';
    case ALWAYS = 'always';

    /**
     * @return array<string>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $item) => $item->value,
            self::cases(),
        );
    }
}
