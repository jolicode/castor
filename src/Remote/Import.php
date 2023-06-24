<?php

namespace Castor\Remote;

use Castor\GlobalHelper;
use Castor\PlatformUtil;
use Castor\Remote\Exception\ImportError;
use Castor\Remote\Exception\InvalidImportUrl;
use Castor\Remote\Exception\NotTrusted;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\fs;
use function Castor\get_cache;
use function Castor\get_input;
use function Castor\io;
use function Castor\log;
use function Castor\run;

/** @internal */
class Import
{
    public static function importFunctions(string $scheme, string $url, bool $dryRun = false): string
    {
        if ('github' === $scheme) {
            if (!preg_match('#^(?<organization>[^/]+)/(?<repository>[^/]+)(?<function_path>[^@]*)@(?<version>.+)$#', $url, $matches)) {
                throw new InvalidImportUrl('The import path from GitHub repository must be formatted like this: "github://<organization>/<repository>/<function_path>@<version>".');
            }

            $path = self::importFunctionsFromGitRepository(
                'github.com',
                sprintf('%s/%s', $matches['organization'], $matches['repository']),
                $matches['version'],
                $matches['function_path'] ?? '/castor.php',
                $dryRun,
            );

            log('Using functions from remote resource.', 'info', [
                'url' => $path,
            ]);

            return $path;
        }

        throw new InvalidImportUrl(sprintf('The import scheme "%s" is not supported.', $scheme));
    }

    private static function importFunctionsFromGitRepository(string $domain, string $repository, string $version, string $functionPath, bool $dryRun): string
    {
        self::ensureTrustedResource($domain . '/' . $repository);

        $home = PlatformUtil::getUserDirectory();
        $dir = ($home ? $home . '/castor' : sys_get_temp_dir()) . '/remote/' . $domain . '/' . $repository . '/' . $version;

        if (!$dryRun) {
            if (fs()->exists($dir . $functionPath)) {
                return $dir . $functionPath;
            }

            log("Importing functions in path {$functionPath} from {$domain}/{$repository} (version {$version})");

            fs()->remove($dir);
            fs()->mkdir($dir);

            $git = (new ExecutableFinder())->find('git');

            if (!$git) {
                throw new ImportError(sprintf('Could not import resources from "%s" because git is not installed.', $domain . '/' . $repository));
            }

            try {
                run(['git', 'clone', "git@{$domain}:{$repository}.git", '--branch', $version, '--depth', '1', '--filter', 'blob:none', '.'], path: $dir, quiet: true);
            } catch (\Throwable $t) {
                throw new ImportError(sprintf('Could not import resources from "%s" because git operation failed.', $domain . '/' . $repository), 0, $t);
            }
        }

        return $dir . $functionPath;
    }

    private static function ensureTrustedResource(string $url): void
    {
        $input = get_input();
        $io = io();

        // Need to look for the raw options as the input is not yet parsed
        $trust = $input->getParameterOption('--trust', false);
        $noTrust = $input->getParameterOption('--no-trust', false);

        if (false !== $trust) {
            return;
        }

        if (false !== $noTrust) {
            throw new NotTrusted($url);
        }

        $trustKey = sprintf('remote.trust.%s', str_replace('/', '.', $url));
        $cache = get_cache();
        $trustChoiceCache = $cache->getItem($trustKey);

        if (null !== $trustChoiceCache->isHit()) {
            $trustChoice = TrustEnum::tryFrom($trustChoiceCache->get());

            if (TrustEnum::NEVER === $trustChoice) {
                throw new NotTrusted($url);
            }

            if (TrustEnum::ALWAYS === $trustChoice) {
                return;
            }
        }

        static $displayTrustWarning = true;

        if ($displayTrustWarning) {
            $io->warning('Your Castor project tries to import functions from external resources.');

            $displayTrustWarning = false;
        }

        $action = TrustEnum::from(
            $io->choice(
                sprintf('Trust <comment>%s</comment> and import?', $url),
                TrustEnum::toArray(),
                TrustEnum::NOT_NOW->value,
            )
        );

        if (TrustEnum::ALWAYS === $action || TrustEnum::NEVER === $action) {
            log('Persisting trust choice for', context: [
                'url' => $url,
                'choice' => $action->value,
            ]);

            $trustChoiceCache->set($action->value);
            $cache->save($trustChoiceCache);
        }

        if (TrustEnum::NOT_NOW === $action || TrustEnum::NEVER === $action) {
            throw new NotTrusted($url);
        }
    }
}
