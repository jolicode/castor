# Repacking your application in a new phar

You have created a castor application, with many tasks, and you want to
distribute it as a single phar file? Castor can help you with that.

In your project, install castor as a dependency:

```bash
composer require castor/castor
```

You'll also need
[box](https://github.com/box-project/box/blob/main/doc/installation.md#installation)
to create the phar. The box binary must be available in your path.

You'll also need to ensure the phar creation is allowed by your PHP
configuration. See the [PHP
documentation](https://www.php.net/manual/en/phar.configuration.php#ini.phar.readonly) to disabled
`phar.readonly`.

Then, run the repack command to create the new phar:

```
vendor/bin/castor repack
```

See the help for more options:

```
vendor/bin/castor repack --help
```

> **Note**: Castor will automatically import all files in the current directly.
> So ensure to have the less files possible in the directory where you run the
> repack command to avoid including useless files in the phar.
