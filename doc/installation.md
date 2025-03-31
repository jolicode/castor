# Installation and Autocomplete

## Installation

We provide several ways to install Castor, depending on your needs:

- [with the installer](#with-the-installer): **recommended** for Linux and macOS;
- [as a phar](#as-a-phar);
- [as a static binary](#as-a-static-binary) if you don't have PHP installed;
- [globally with Composer](#globally-with-composer): **not recommended**;
- [manually with git](#manually-with-git);
- [in a Github Action](#in-a-github-action).

### With the installer

> [!TIP]
> This is the recommended way to install Castor on Linux and macOS. It requires
> PHP >= 8.2.

```bash
curl "https://castor.jolicode.com/install" | bash
```

### As a phar

You can download the latest release of Castor as a phar file from the [releases
page](https://github.com/jolicode/castor/releases).

> [!NOTE]
> You can also download the latest version by browsing [the build
> page](https://castor.jolicode.com/install/latest) and
> selecting the last build.

We provide different phar for Linux / MacOS / Windows architectures to offer
lighter phar files. Download the correct one and make it available in your
shell.

=== "Linux AMD64 (x86-64)"

    > [!TIP]
    > On Linux, it's better to install the phar with the [installer](#with-the-installer)
    > as it handles everything for you.

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-amd64.phar" -Lfso $HOME/.local/bin/castor && \
        chmod u+x $HOME/.local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

    > [!CAUTION]
    > When using Windows Subsystem for Linux (WSL), you should still use the
    > Windows phar instead of the Linux phar.

=== "Linux ARM64"

    > [!TIP]
    > On Linux, it's better to install the phar with the [installer](#with-the-installer)
    > as it handles everything for you.

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-arm64.phar" -Lfso $HOME/.local/bin/castor && \
        chmod u+x $HOME/.local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "macOS with Apple Silicon (M1, M2, M3)"

    > [!TIP]
    > On macOS, it's better to install the phar with the [installer](#with-the-installer)
    > as it handles everything for you.

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-arm64.phar" -Lfso /usr/local/bin/castor && \
        chmod u+x /usr/local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "macOS with Intel"

    > [!TIP]
    > On macOS, it's better to install the phar with the [installer](#with-the-installer)
    > as it handles everything for you.

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-amd64.phar" -Lfso /usr/local/bin/castor && \
        chmod u+x /usr/local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "Windows or WSL"

    ```bash
    curl.exe "https://github.com/jolicode/castor/releases/latest/download/castor.windows-amd64.phar" -Lso C:\<a directory in your PATH>\castor
    ```

### As a static binary

If you don't have PHP installed on your system, Castor can also be installed
with a static binary that embeds PHP, so it can be run anywhere. The static
binaries are available for Linux and MacOS only.

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --static
```

You can also download the binaries in the [releases
page](https://github.com/jolicode/castor/releases):

### Globally with Composer

> [!WARNING]
> Installing Castor globally with Composer is not recommended. Installing CLI
> tools globally with Composer may easily lead to conflicts in the "global"
> project.

You can install Castor globally with Composer:

```bash
composer global require jolicode/castor
```

Then make sure that the Composer global bin directory is in your `PATH`.

> [!NOTE]
> The global Composer path may vary depending on your operating system.

You can run the following command to determine it:

```bash
composer config --list --global | grep -F "[home]"

# It may looks like this on some Linux systems:
# [home] /home/<your_username>/.config/composer
# Or like this too:
# [home] /home/<your_username>/.composer
```

You can optionally replace `/home/<your_username>` with the Unix
`$HOME` environment variable. Now, append `/vendor/bin` to that path to get the
Composer global bin directory to add to your `PATH`:

```bash
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```

Any binary globally installed with Composer will now work everywhere.

### Manually with git

You'll need to clone the repository and run `composer install` to
install the project. Then create a symlink to the `castor` file in your `PATH`.

```bash
cd $HOME/somewhere
git clone git@github.com:jolicode/castor.git
cd castor
composer install
ln -s $PWD/bin/castor $HOME/.local/bin/castor
```

### In a Github Action

#### Using setup-castor action

Castor provide a [Github Action to install Castor in your workflow](https://github.com/marketplace/actions/setup-castor).
Here is an example:

```yaml
jobs:
  my-job:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup castor
        uses: castor-php/setup-castor@v0.1.0

      - name: Run castor "hello" task
        run: castor hello
```

This action will use the static binary to install Castor in your workflow, so you will not
need to have PHP installed on the runner.

#### Using setup-php action

If you need PHP, it can also be installed in a Github Action by using the action `shivammathur/setup-php@v2` and specifying
`castor` in the `tools` option. This will configure PHP with the right version but also make castor available
in the next steps. Here is an example:

```yaml
jobs:
  my-job:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: castor

      - name: Run castor "hello" task
        run: castor hello
```

## Autocomplete

Castor provides a built-in autocomplete to ease its usage in shell.

See [the dedicated documentation](going-further/interacting-with-castor/autocomplete.md)
to see how to install it, and also how to autocomplete your arguments.

## Stubs

The first time you run castor, it will create a `.castor.stub.php` at the root
directory of your project (where your `castor.php` is). This file contains some
definition of classes and methods from Castor and some of its dependencies.

This is useful when you install Castor from a PHAR, from a global composer
install, etc. Without it, your IDE would complain that it does not understand some
classes and would not provide any autocompletion in your castor files.

We suggest you to add this file to your `.gitignore` to not version it in git.
Castor will automatically update this file the first time you run Castor after
you install or update it.

> [!TIP]
> If you want to analyze your tasks with PHPStan, you will need to make PHPStan
> aware of some classes and functions definitions from Castor and its dependencies.
> To achieve this, add the stubs file in the `scanFiles` section of your `phpstan.neon`
> configuration file:

```neon
parameters:
    # ...
    scanFiles:
        - .castor.stub.php
```

If you don't want to generate stubs, you can use the `CASTOR_GENERATE_STUBS`
environment variables:

```
CASTOR_GENERATE_STUBS=0 castor
```

For convenience, you can export this variable in your shell configuration file:

```bash
echo 'export CASTOR_GENERATE_STUBS=0' >> ~/.bashrc
source ~/.bashrc
```
