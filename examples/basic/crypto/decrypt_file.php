<?php

namespace crypto;

use Castor\Attribute\AsTask;

use function Castor\decrypt_file_with_password;

#[AsTask(description: 'Decrypt file with a password')]
function decrypt_file(string $file): void
{
    decrypt_file_with_password($file, 'my super secret password');
}
