# Repacking your application in a new phar

You have created a Castor application, with many tasks, and you want to
distribute it as a single phar file? Castor can help you with that.

## Pre-requisites

In your project, install Castor as a dependency:

```bash
composer require jolicode/castor
```

You'll also need
[box](https://github.com/box-project/box/blob/main/doc/installation.md#installation)
to create the phar. The box binary must be available in your path.

You'll also need to ensure the phar creation is allowed by your PHP
configuration. See the [PHP
documentation](https://www.php.net/manual/en/phar.configuration.php#ini.phar.readonly) to disabled
`phar.readonly`.

## Running the Repack Command

Then, run the repack command to create the new phar:

```
vendor/bin/castor repack
```

See the help for more options:

```
vendor/bin/castor repack --help
```

> [!NOTE]
> Castor will automatically import all files in the current directly.
> So ensure to have the less files possible in the directory where you run the
> repack task to avoid including useless files in the phar.

> [!NOTE]
> If a `box.json` file exists in your application directory,
> it will be merged with the config file used by Castor.
> None of theses keys `base-path`, `main`, `alias` or `output` keys can be
> defined in your application box config.

> [!CAUTION]
>  If some classes are missing in your phar, it might be because they are
> excluded by castor's `box.json` file. In this case, you should override the
> default configuration with a local `box.json` file

## Going further

Packaging your Castor app as a phar simplifies distribution but requires PHP
setup on target systems.

[Castor's `compile` command](compile.md) streamlines this by embedding the phar
in a PHP binary, creating a static executable for diverse environments.
