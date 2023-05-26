<?php

namespace Castor\Tests;

class OutputCleaner
{
    public static function cleanOutput(string $string): string
    {
        $string = str_replace("\r\n", "\n", $string);

        return str_replace(\dirname(__DIR__, 1), '...', $string);
    }
}
