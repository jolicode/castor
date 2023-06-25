<?php

namespace Castor\Remote;

use Castor\GlobalHelper;
use Castor\Remote\Exception\NotTrusted;
use Symfony\Contracts\Cache\ItemInterface;

use function Castor\cache;
use function Castor\fs;
use function Castor\get_input;
use function Castor\io;
use function Castor\log;
use function Castor\run;

/** @internal */
class Import
{
    public static function importFunctionsFromGitRepository(string $domain, string $repository, string $version, string $functionPath): string
    {
        self::ensureTrustedResource($domain . '/' . $repository);

        $dir = GlobalHelper::getGlobalDirectory() . '/remote/' . $domain . '/' . $repository . '/' . $version;

        if (!is_dir($dir)) {
            log("Importing functions in path {$functionPath} from {$domain}/{$repository} (version {$version})");

            fs()->mkdir($dir);

            run(['git', 'clone', "git@{$domain}:{$repository}.git", '--branch', $version, '--depth', '1', '--filter', 'blob:none', '.'], path: $dir, quiet: true);
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
            throw new NotTrusted($url, false);
        }

        $trustKey = sprintf('remote.trust.%s', str_replace('/', '.', $url));

        $trustChoice = cache(
            $trustKey,
            function (ItemInterface $item) {
                if ($item->isHit()) {
                    return $item->get();
                }

                $item->expiresAfter(-1);

                return null;
            },
        );

        if (null !== $trustChoice) {
            $trustChoice = TrustEnum::tryFrom($trustChoice);

            if (TrustEnum::NEVER === $trustChoice) {
                throw new NotTrusted($url, false);
            }

            if (TrustEnum::ALWAYS === $trustChoice) {
                return;
            }
        }

        static $displayTrustWarning = true;

        if ($displayTrustWarning) {
            $io->warning('Your Castor project tries to import functions from external resources');

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

            cache($trustKey, fn () => $action->value);
        }

        if (TrustEnum::NOT_NOW === $action || TrustEnum::NEVER === $action) {
            throw new NotTrusted($url, true);
        }
    }
}
