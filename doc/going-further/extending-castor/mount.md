# Mount another application

Castor can mount another application. This pattern is useful when you have a
majestic monolith and you want to split it into smaller applications:

```
project/
├─ projects/
│  ├─ A/
│  │  ├─ castor.php
│  ├─ B/
│  │  ├─ castor.php
├─ castor.php
```

In this example, `project/A/castor.php` and `project/B/castor.php` are two
standalone castor applications. All functions defined in `project/A/castor.php`
will be run **inside** the `project/A` directory whatever your current directory
is.

To achieve this, you need to use the `mount()` function in your main
`castor.php`:

```php
use function Castor\mount;

mount('projects/A');
mount('projects/B');
```

You can also prefix a namespace to all task found in each mounted application:

```php
use function Castor\mount;

mount('projects/A', 'project:a');
mount('projects/B', 'project:b');
```

## `mount()` vs `import()`

You may wonder when to use `mount()` vs `import()`. This really depends on the
working directory. If in your `path/to/another/castor/app` you keep repeating the
kind of code:

```php
use function Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foobar() {
    run($command, context: context()->withWorkingDirectory(__DIR__));
}
```

Then you should use `mount()` to avoid repeating the `workingDirectory`
argument.

Otherwise, you can continue to use `import()`.
