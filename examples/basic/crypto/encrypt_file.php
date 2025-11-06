<?php

namespace crypto;

use Castor\Attribute\AsTask;

use function Castor\encrypt_file_with_password;

#[AsTask(description: 'Encrypt file with a password')]
function encrypt_file(string $file): void
{
    encrypt_file_with_password($file, 'my super secret password');
}
