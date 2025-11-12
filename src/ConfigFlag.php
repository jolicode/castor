<?php

namespace Castor;

enum ConfigFlag
{
    case ContextAwareFilesystem;

    public function description(): string
    {
        return match ($this) {
            self::ContextAwareFilesystem => 'Context-aware filesystem with automatic path resolution',
        };
    }

    public function willBeDefaultInVersion(): string
    {
        return match ($this) {
            self::ContextAwareFilesystem => '2.0',
        };
    }

    public function defaultValueWhenNull(): bool
    {
        return match ($this) {
            self::ContextAwareFilesystem => false,
        };
    }
}
