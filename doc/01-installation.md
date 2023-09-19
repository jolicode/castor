# Installation and Autocomplete

## Installation

> **Note**
> Castor requires PHP >= 8.1 to run.

### As a phar - recommended way

You can download the latest release of Castor as a phar file from the [releases
page](https://github.com/jolicode/castor/releases).

You can also download the latest version by browsing [the build
page](https://github.com/jolicode/castor/actions/workflows/build-phar.yml) and
selecting the last build.

We provide different phar for Linux / MacOS / Windows architectures to offer lighter phar
files. Download the correct one and make it available in your shell:

Example for Linux:
```bash
curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-amd64.phar" -Lso $HOME/.local/bin/castor && chmod u+x $HOME/.local/bin/castor
```

Example for MacOS:
```bash
curl "https://github.com/jolicode/castor/releases/latest/download/castor.darwin-amd64.phar" -Lso /usr/local/bin/castor && chmod u+x /usr/local/bin/castor
```

Example for Windows:
```bash
curl "https://github.com/jolicode/castor/releases/latest/download/castor.windows-amd64.phar" -Lso $HOME/.local/bin/castor && chmod u+x $HOME/.local/bin/castor
```

### Globally with Composer

You can install Castor globally with Composer:

```bash
composer global require jolicode/castor
```

Then make sure that the Composer global bin directory is in your `PATH`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

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

### With Docker

If you don't have PHP >= 8.1 installed on your host, you can use Docker to run castor.
However, some features like notifications will not work.

We ship a `Dockerfile` that you can use to build a Docker image with castor:

```
docker build -t castor .
```

Then you can run castor with:

```
docker run -it --rm -v `pwd`:/project castor
```

If you want to use Docker commands in your tasks, you must enable Docker
support when building the image:

```
docker build -t castor --build-arg WITH_DOCKER=1  .
```

Then you can run castor with:

```
docker run -it --rm -v `pwd`:/project -v "/var/run/docker.sock:/var/run/docker.sock:rw" castor
```

We suggest you to create an alias for it:

```
alias castor='docker run -it --rm -v `pwd`:/project -v "/var/run/docker.sock:/var/run/docker.sock:rw" castor'
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
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: castor

      - name: Run castor "hello" task
        run: castor hello
```

## Autocomplete

If you use bash, you can enable autocomplete for castor by executing the
following commands:

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
