<?php

namespace Castor\Tests;

final class OutputCleaner
{
    public static function cleanOutput(string $string): string
    {
        $string = str_replace("\r\n", "\n", $string);
        // In the bash completion script ×2
        $string = str_replace('_sf_castor.linux-amd64.phar', '_sf_castor', $string);
        $string = str_replace('castor.linux-amd64.phar', 'castor', $string);
        $string = preg_replace('{In functions.php line \d+:}m', 'In functions.php line XXXX:', $string);
        $string = preg_replace('{In Process.php line \d+:}m', 'In Process.php line XXXX:', $string);
        $string = preg_replace('{In ContextRegistry.php line \d+:}m', 'In ContextRegistry.php line XXXX:', $string);
        $string = preg_replace('{you are using v\d+.\d+.\d+.}m', 'you are using vX.Y.Z.', $string);
        $string = preg_replace('{you are using v\d+.\d+.\d+.}m', 'you are using vX.Y.Z.', $string);

        return str_replace(\dirname(__DIR__, 1), '...', $string);
    }
}
