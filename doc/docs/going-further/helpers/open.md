# Open URLs and files

Castor provides an `open()` function that will open one or more
URLs or files in the user's default application.

```php
use Castor\Attribute\AsTask;

use function Castor\open;

#[AsTask()]
function open()
{
    open('https://castor.jolicode.com');
}
```
