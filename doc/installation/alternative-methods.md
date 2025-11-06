---
description: Alternatives methods to install Castor.
---

# Alternative installation methods

Using the installer is [the recommended way to install Castor](installer.md). However, if you
need to (or if you are on Windows), you can also install Castor using other
methods.

## As a phar

We provide different phar for Linux, MacOS and Windows architectures. It allows
us to distribute phar files that contains only necessary stuff for your system.

Download the correct phar for your OS and architecture from the [releases page](https://github.com/jolicode/castor/releases)
and make it available in your shell:

=== "Linux AMD64 (x86-64)"

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

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-arm64.phar" -Lfso $HOME/.local/bin/castor && \
        chmod u+x $HOME/.local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "macOS with Apple Silicon"

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-arm64.phar" -Lfso /usr/local/bin/castor && \
        chmod u+x /usr/local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "macOS with Intel"

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

> [!NOTE]
> You can also download the latest phar by browsing [the build
> page](https://castor.jolicode.com/install/latest) and
> selecting the last build.

## As a static binary

If you don't have PHP available on your system, Castor can be installed with a
static binary that embeds PHP.

> [!NOTE]
> At the moment, static binaries are only available for Linux and MacOS.

Download the correct binary for your OS and architecture from the [releases page](https://github.com/jolicode/castor/releases)
and make it available in your shell:

=== "Linux AMD64 (x86-64)"

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-amd64" -Lfso $HOME/.local/bin/castor && \
        chmod u+x $HOME/.local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "Linux ARM64"

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-arm64" -Lfso $HOME/.local/bin/castor && \
        chmod u+x $HOME/.local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "macOS with Apple Silicon"

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-arm64" -Lfso /usr/local/bin/castor && \
        chmod u+x /usr/local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

=== "macOS with Intel"

    ```bash
    curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-amd64" -Lfso /usr/local/bin/castor && \
        chmod u+x /usr/local/bin/castor && \
        castor --version || \
        (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
    ```

> [!NOTE]
> You can also download the latest binary by browsing [the build
> page](https://castor.jolicode.com/install/latest) and
> selecting the last build.

## Globally with Composer

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

```console
$ composer config --list --global | grep -F "[home]"

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

## Manually with git

You'll need to clone the repository and run `composer install` to
install the project. Then create a symlink to the `castor` file in your `PATH`:

```bash
cd $HOME/somewhere
git clone git@github.com:jolicode/castor.git
cd castor
composer install
ln -s $PWD/bin/castor $HOME/.local/bin/castor
```

<!-- markdownlint-disable-file code-block-style -->
