# Installation and Autocomplete

## Installation

> [!NOTE]
> Castor requires PHP >= 8.1 to run.

### As a phar - recommended way

You can download the latest release of Castor as a phar file from the [releases
page](https://github.com/jolicode/castor/releases).

You can also download the latest version by browsing [the build
page](https://castor.jolicode.com/install/latest) and
selecting the last build.

We provide different phar for Linux / MacOS / Windows architectures to offer lighter phar
files. Download the correct one and make it available in your shell:

#### Linux

```bash
curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-amd64.phar" -Lfso $HOME/.local/bin/castor && \
    chmod u+x $HOME/.local/bin/castor && \
    castor --version || \
    (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
```

#### MacOS with Apple Silicon

For Mac with Apple Silicon processors (M1, M2, M3, etc).

```bash
curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-arm64.phar" -Lfso /usr/local/bin/castor && \
    chmod u+x /usr/local/bin/castor && \
    castor --version || \
    (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
```

#### MacOS with Intel

For Mac with old Intel processors.

```bash
curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-amd64.phar" -Lfso /usr/local/bin/castor && \
    chmod u+x /usr/local/bin/castor && \
    castor --version || \
    (echo "Could not install castor. Is the target directory writeable?" && (exit 1))
```

#### Windows

```bash
curl.exe "https://github.com/jolicode/castor/releases/latest/download/castor.windows-amd64.phar" -Lso C:\<a directory in your PATH>\castor
```

### Globally with Composer - not recommended way

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

### Manually

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

Castor can also be installed in a Github Action by using the action `shivammathur/setup-php@v2` and specifying
`castor` in the `tools` option. This will configure PHP with the right version but also make castor available
in the next steps. Here is an example:

```bash
jobs:
  my-job:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: castor

      - name: Run castor "hello" task
        run: castor hello
```

## Autocomplete

If you use bash, you can enable autocomplete for castor by running the
following task:

```
castor completion | sudo tee /etc/bash_completion.d/castor
```

Then reload your shell.

Others shells are also supported (zsh, fish, etc). To get the list of supported
shells and their dedicated instructions, run:

```
castor completion --help
```

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
