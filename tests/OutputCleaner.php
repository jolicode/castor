<?php

namespace Castor\Tests;

class OutputCleaner
{
    public static function cleanOutput(string $string): string
    {
        $string = str_replace("\r\n", "\n", $string);
        $string = preg_replace('{In functions.php line \\d+:}m', 'In functions.php line XXXX:', $string);
        $string = preg_replace('{you are using v\\d+.\\d+.\\d+.}m', 'you are using vX.Y.Z.', $string);

        return str_replace(\dirname(__DIR__, 1), '...', $string);
    }
}
