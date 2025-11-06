<?php

namespace crypto;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\encrypt_with_password;
use function Castor\io;

#[AsTask(description: 'Encrypt content with a password')]
function encrypt(#[AsArgument()] string $content = 'Hello you!'): void
{
    io()->writeln(encrypt_with_password($content, 'my super secret password'));
}
