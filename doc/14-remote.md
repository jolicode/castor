# Import remote functions

Castor can import functions from your filesystem but also from a remote resource.

## Importing functions

When importing functions from a remote resource, Castor will download the files
and store them in `$HOME/.castor/remote/`.

### From a GitHub repository

To import functions from a GitHub repository, pass a path to the `import()`
function, formatted like this:

```
github://<user>/<repository>/<version>/<path of the php file to import>
```

Here is an example:

```php
use function Castor\import;

import('github://pyrech/castor-setup-php/main/castor.php');

#[AsTask()]
function hello(): void
{
    \pyrech\helloWorld();
}
```

> **Note**
> If path of the file is empty, it will import the `castor.php` file at the root
> of the repository.
