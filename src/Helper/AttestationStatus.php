<?php

namespace Castor\Helper;

/** @internal */
enum AttestationStatus
{
    /** The GitHub CLI is not installed or not authenticated: no check possible */
    case Skipped;
    case Verified;
    /** No attestation exists for this file (e.g. a release published before attestations) */
    case NotAttested;
}
