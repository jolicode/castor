# Cryptography helpers

## The `encrypt_with_password()` function

Castor provides a `encrypt_with_password()` function to allow to encrypt a
content with a password:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\encrypt_with_password;
use function Castor\io;

#[AsTask(description: 'Encrypt content with a password')]
function encrypt(#[AsArgument()] string $content = "Hello you!"): void
{
    io()->writeln(encrypt_with_password($content, 'my super secret password'));
}
```

> [!NOTE]
> Under the hood, Castor use libsodium for encryption.

## The `decrypt_with_password()` function

Castor provides a `decrypt_with_password()` function to allow to decrypt a
content with a password:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\decrypt_with_password;
use function Castor\io;

#[AsTask(description: 'Decrypt content with a password',)]
function decrypt(string $content): void
{
    io()->writeln(decrypt_with_password($content, 'my super secret password'));
}
```

## The `encrypt_file_with_password()` function

Castor provides a `encrypt_file_with_password()` function to allow to encrypt a
file with a password:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\encrypt_file_with_password;
use function Castor\io;

#[AsTask(description: 'Encrypt file with a password')]
function encrypt_file(string $file): void
{
    encrypt_file_with_password($file, 'my super secret password');
}
```

## The `decrypt_file_with_password()` function

Castor provides a `decrypt_file_with_password()` function to allow to decrypt a
file with a password:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\decrypt_file_with_password;
use function Castor\io;

#[AsTask(description: 'Decrypt file with a password')]
function decrypt_file(string $file): void
{
    decrypt_file_with_password($file, 'my super secret password');
}
```

> [!NOTE]
> When no destination path is provided, the function follows these rules:
> 
> For files ending with `.enc` extension:
> - The function attempts to create the decrypted file with the same name but without the `.enc` extension
>   - Example: `secret.txt.enc` → `secret.txt`
> - If a file already exists at that path, it uses the `.dec` extension instead to prevent data loss
>   - Example: `secret.txt.enc` → `secret.txt.dec` (when `secret.txt` already exists)
> 
> For files not ending with `.enc` extension:
> - The function always appends the `.dec` extension to the original filename
>   - Example: `encrypted_data` → `encrypted_data.dec`
