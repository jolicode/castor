<?php

namespace crypto;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\decrypt_file_with_password;
use function Castor\decrypt_with_password;
use function Castor\encrypt_file_with_password;
use function Castor\encrypt_with_password;
use function Castor\io;

#[AsTask(description: 'Encrypt content with a password')]
function encrypt(#[AsArgument()] string $content = 'Hello you!'): void
{
    io()->writeln(encrypt_with_password($content, 'my super secret password'));
}

#[AsTask(description: 'Decrypt content with a password', )]
function decrypt(string $content): void
{
    io()->writeln(decrypt_with_password($content, 'my super secret password'));
}

#[AsTask(description: 'Encrypt file with a password')]
function encrypt_file(string $file): void
{
    encrypt_file_with_password($file, 'my super secret password');
}

#[AsTask(description: 'Decrypt file with a password')]
function decrypt_file(string $file): void
{
    decrypt_file_with_password($file, 'my super secret password');
}
