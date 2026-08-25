<?php

namespace Castor\Helper;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Checks that a file was built by the Castor GitHub Actions workflow, using
 * the artifact attestation published with each build.
 *
 * This needs the GitHub CLI, installed and authenticated: the attestations API
 * rejects anonymous requests.
 *
 * @internal
 */
final readonly class AttestationHelper
{
    /**
     * @throws \RuntimeException when the attestation exists but does not match
     */
    public function verify(string $file): AttestationStatus
    {
        $gh = new ExecutableFinder()->find('gh');

        if (null === $gh || 0 !== new Process([$gh, 'auth', 'status'])->run()) {
            return AttestationStatus::Skipped;
        }

        $process = new Process([$gh, 'attestation', 'verify', $file, '--repo', 'jolicode/castor']);
        $process->setTimeout(60);
        $process->run();

        if ($process->isSuccessful()) {
            return AttestationStatus::Verified;
        }

        $error = trim($process->getErrorOutput());

        if (str_contains($error, 'HTTP 404') || str_contains($error, 'no attestations found')) {
            return AttestationStatus::NotAttested;
        }

        throw new \RuntimeException(\sprintf("The provenance of \"%s\" could not be verified:\n%s", $file, $error));
    }
}
