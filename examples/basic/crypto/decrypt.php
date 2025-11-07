<?php

namespace crypto;

use Castor\Attribute\AsTask;

use function Castor\decrypt_with_password;
use function Castor\io;

#[AsTask(description: 'Decrypt content with a password', )]
function decrypt(string $content): void
{
    io()->writeln(decrypt_with_password($content, 'my super secret password'));
}
