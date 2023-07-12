# Import remote functions

Castor can import functions from your filesystem but also from a remote resource.

## Importing functions

When importing functions from a remote resource, Castor will download the files
and store them in `$HOME/.castor/remote/`.

### From a GitHub repository

To import functions from a GitHub repository, pass a path to the `import()`
function, formatted like this:

```
github://<user>/<repository>/<path of the php file to import>@<version>
```

Here is an example:

```php
use function Castor\import;

import('github://pyrech/castor-setup-php/castor.php@main');

#[AsTask()]
function hello(): void
{
    \pyrech\helloWorld();
}
```

> **Note**
> If path of the file is empty, it will import the `castor.php` file at the root
> of the repository.

## Trusting remote resource

For security reasons, each time your Castor project tries to import a remote
resource, Castor will warn you to ask if you trust it.

For each remote resource, Castor will ask you what to do. You can either:
- `not now`: Castor will **not import** the function but will **ask you again**
next time ;
- `never`: Castor will **not import** the function and will persist your choice
to **not ask you again** in the future ;
- `only this time`: Castor will **import** the function but will ask you again the
next time ;
- `always`: Castor will **import** the function and will persist your choice
  to **not ask you again** in the future.

You can also pass the `--trust` (or `--no-trust`) options to automatically trust
(or not) **all** remote resources without being asked for.

> **Warning**
> The `--trust` option should be used with caution as it could lead to malicious
> code execution. The main use case of this option is to be used in a Continuous
> Integration environment.
