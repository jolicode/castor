# Root file and shebang

By default castor looks for a `castor.php` or  `.castor/castor.php` file in the
current working directory or one of its parents.

However you can define another root file by using the `--castor-file` option:

```bash
castor --castor-file=path/to/your-file.php your-task
```

This could be very useful if you want to use castor with a shebang line. For example, you
can create a file named `my-script` with the following content:

```php
#!/usr/bin/env -S castor --castor-file
<?php
use Castor\Attribute\AsTask;

#[AsTask()]
function myTask() {
    // Your task implementation
}
```

Make sure to give execute permissions to your script:

```bash
chmod +x my-script
```

Now you can run your script directly from the command line:

```bash
./my-script my-task
```
