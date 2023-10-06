<?php

namespace Castor\Fingerprint;

enum FileHashStrategy
{
    case Content;
    case MTimes;
}
