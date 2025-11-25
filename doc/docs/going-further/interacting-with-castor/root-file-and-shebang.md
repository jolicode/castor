# Root file and shebang

## Using a custom root file

By default castor looks for a `castor.php` or  `.castor/castor.php` file in the
current working directory or one of its parents.

However you can use another root file by using the `--castor-file` option:

```bash
castor --castor-file=path/to/your-file.php your-task
```

## Using a shebang line

Unix systems support shebang lines to execute scripts directly from the command
line, without having to prefix them with the interpreter.

The `--castor-file` option makes it possible to create a Castor file that can be
executed directly. For example, you can create a file named `my-script` with the
following content:

```php
{% include "/examples/advanced/castor-file/shebang.php" %}
```

Make sure to give execute permissions to your script:

```bash
chmod +x my-script
```

Now, you can run your script directly from the command line:

```bash
./my-script shebang-task
```
