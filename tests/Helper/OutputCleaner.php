<?php

namespace Castor\Tests\Helper;

final class OutputCleaner
{
    public static function cleanOutput(string $string): string
    {
        $string = str_replace("\r\n", "\n", $string);

        // In the bash completion script ×2
        $string = str_replace('_sf_castor.linux-amd64.phar', '_sf_castor', $string);
        $string = str_replace('castor.linux-amd64.phar', 'castor', $string);
        $string = str_replace('_sf_castor.linux-amd64', '_sf_castor', $string);
        $string = str_replace('castor.linux-amd64', 'castor', $string);

        // SSH tests behave differently between local and CI
        $string = str_replace("scp: Connection closed\n", '', $string);
        $string = str_replace("lost connection\n", '', $string);

        // Phar, trace
        $string = str_replace(\dirname(__DIR__, 2), '...', $string);
        $string = str_replace('phar://', '', $string);
        $string = str_replace('tools/phar/build/castor/', '', $string);
        $string = str_replace('.../castor/', '.../', $string);
        $string = preg_replace("{require\\(\\) at .*/castor:\\d+\n}", '', $string);

        // Clean line numbers
        $string = preg_replace('{In (([A-Z]|[a-z])\w+).php line \d+:}m', 'In \1.php line XXXX:', $string);
        $string = preg_replace('{\.php:\d+}m', '.php:XXXX', $string);
        $string = preg_replace('{castor:\d+}m', 'castor:XXXX', $string);

        // Process has not always the same exit number
        $string = preg_replace('{The following process did not finish successfully \(exit code \d+\):}m', 'The following process did not finish successfully (exit code XX): ', $string);

        // Clean the time
        $string = preg_replace('{^\d\d:\d\d:\d\d }m', 'hh:mm:ss ', $string);

        // Clean the warning on tasks when remote imports are disabled
        $string = preg_replace('{hh:mm:ss WARNING   \[castor\] Could not import "[\w:/\.-]*": Remote imports are disabled\.}m', '', $string);

        // Fix notification logs
        $string = preg_replace('{hh:mm:ss ERROR     \[castor\] Failed to send notification\.}m', '', $string);

        // Avoid spacing issues
        $string = ltrim($string, "\n"); // Trim output start to avoid empty lines (like after removing remote import warnings)
        $string = preg_replace('/ +$/m', '', $string); // Remove trailing space

        // Fix the watcher path when running the tests with local project VS in the phar / static
        $string = str_replace('.../src/Runner/../../tools/watcher/bin/watcher-linux-amd64', 'watcher', $string);
        $string = str_replace('/tmp/watcher-linux-amd64', 'watcher', $string);

        // composer version
        $string = preg_replace('{Composer version \d+.\d+.\d+ \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}}', 'Composer version 1.2.3', $string);

        // castor version
        $string = preg_replace('{you are using v\d+.\d+.\d+.}m', 'you are using vX.Y.Z.', $string);

        return preg_replace('{castor v\d+.\d+.\d+}m', 'castor v.X.Y.Z', $string);
    }
}
