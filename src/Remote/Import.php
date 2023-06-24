<?php

namespace Castor\Remote;

use Castor\GlobalHelper;

use function Castor\fs;
use function Castor\log;
use function Castor\run;

/** @internal */
class Import
{
    public static function importFunctionsFromGitRepository(string $domain, string $repository, string $version, string $functionPath): string
    {
        $dir = GlobalHelper::getGlobalDirectory() . '/remote/' . $domain . '/' . $repository . '/' . $version;

        if (!is_dir($dir)) {
            log("Importing functions in path {$functionPath} from {$domain}/{$repository} (version {$version})");

            fs()->mkdir($dir);

            run(['git', 'clone', "git@{$domain}:{$repository}.git", '--branch', $version, '--depth', '1', '--filter', 'blob:none', '.'], path: $dir, quiet: true);
        }

        return $dir . $functionPath;
    }
}
