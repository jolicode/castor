# Compiling your application into a static binary

[Packing your Castor application as a phar](repack.md) can be a good way to
easily share and use it in various environments.

However, you need to ensure that PHP is installed and configured correctly in
all the environments where you want to use your Castor app. This can be a
hassle, especially if you don't have control over the environments.

To make things simpler, Castor's `compile` command can help by creating a
customizable PHP binary with a phar, making one executable file that can be used
in any setting.

Just pass your repacked Castor app phar as an argument of this command.

## Pre-requisites

Follow the [`repack` documentation](repack.md) to produce a phar of your Castor
app.

## Running the Compile Command

To compile your Castor application, navigate to your project directory and run:

```bash
vendor/bin/castor compile my-custom-castor-app.phar
```

> [!WARNING]
> Compiling is not supported yet on Windows.

### Options

Make sure to take a look at the command description to see all the available
options:
```bash
vendor/bin/castor compile --help
```

### Behavior

The `compile` command performs several steps:

1. Downloads or uses an existing [Static PHP CLI
   tool](https://github.com/crazywhalecc/static-php-cli) to compile PHP and the
   phar into a binary.
2. If required, it automatically installs dependencies and compiles PHP with the
   specified extensions.
3. Combines the compiled PHP and your phar file into a single executable.

## Post-Compilation

Once the compilation is finished, your Castor application is transformed into a
static binary named `castor` by default (you can use the `--binary-path` option
to change it).

This binary is now ready to be distributed and run in environments that do not
have PHP installed.

You can simply run it like any other executable:

```bash
./castor
```

