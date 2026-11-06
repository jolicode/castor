---
description: >
  Learn how to compile your Castor application into a static binary, making it
  portable and independent of the target system's PHP installation.
---

# Compiling your application into a static binary

This document explains how to compile your Castor application into a static
binary, making it portable and independent of the target system's PHP
installation.

## Introduction

[Packing your Castor application as a phar](repack.md) can be a good way to
easily share and use it in various environments.

However, you need to ensure that PHP is installed and configured correctly in
all the environments where you want to use your Castor app. This can be a
hassle, especially if you don't have control over the environments.

To make things simpler, Castor's `compile` command can help by creating a
customizable PHP binary with a phar, making one executable file that can be used
in any setting.

Just pass your repacked Castor app phar as an argument of this command and you
are done.

## Pre-requisites

Compiling your Castor application into a static binary requires you to have a phar
version of it. Follow the [`repack` documentation](repack.md) to produce a phar
containing your Castor application.

## Running the Compile Command

To compile your Castor application, navigate to your project directory and run:

```bash
castor compile my-custom-castor-app.phar
```

> [!WARNING]
> Compiling is not supported yet on Windows.

{{ command("castor:compile") }}

### Behavior

The `compile` command performs several steps:

1. Downloads or uses an existing [Static PHP CLI
   tool](https://github.com/crazywhalecc/static-php-cli) to compile PHP and the
   phar into a binary. The downloaded archive is checked against its SHA-256
   checksum: Castor knows the checksums of the default `--spc-version`, any
   other version needs its checksum passed with the `--spc-sha256` option.
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

## Running a web server

The binary runs your tasks with the `micro` SAPI of PHP, which does not include
the [built-in web
server](https://www.php.net/manual/en/features.commandline.webserver.php): there
is no `php -S`, and `PHP_BINARY` is empty.

To start a web server from a task, use the `--php-cli` option, and add the
`pcntl` extension to the default ones:

```bash
castor compile my-custom-castor-app.phar --php-cli --php-extensions=mbstring,phar,posix,tokenizer,curl,filter,openssl,pcntl
```

It also builds a PHP CLI, with the same PHP version and extensions, and embeds
it in the binary as `.castor-php-cli/php` (`.castor-php-cli/php.exe` on
Windows). This adds around 8 MB to the binary.

> [!NOTE]
> Building the PHP CLI requires a working network connection:
> static-php-cli checks the `openssl` extension by connecting to remote hosts.

A binary cannot be executed from inside a phar, and the web server needs a real
directory, so the task copies them first. Add the files to serve to the phar
with a [`box.json` file](repack.md):

```json
{
    "directories": ["web"]
}
```

Then start the server, and print its URL:

```php
#[AsTask(description: 'Start the web interface and print its URL')]
function web(): void
{
    // Neither a binary nor a docroot can be used from inside the phar
    $directory = sys_get_temp_dir() . '/my-app-' . bin2hex(random_bytes(8));
    fs()->mkdir($directory, 0o700);
    fs()->mirror(__DIR__ . '/web', "{$directory}/web");

    // PHP_BINARY is empty in a static binary
    $php = PHP_BINARY;
    if ('' === $php) {
        $php = "{$directory}/php";
        fs()->copy(Phar::running() . '/.castor-php-cli/php', $php);
        fs()->chmod($php, 0o755);
    }

    $url = null;

    run(
        [$php, '-S', '127.0.0.1:0', '-t', "{$directory}/web"],
        context: context()
            ->withEnvironment(['PHP_CLI_SERVER_WORKERS' => '4'])
            // CTRL+C stops the server, not the task: it still has to clean up
            ->withTrappedSignals()
            ->withAllowFailure()
            ->withTimeout(null)
            ->withPty(false)
            ->withTty(false)
            ->withQuiet(),
        callback: static function (string $type, string $bytes) use (&$url): void {
            // Each worker announces itself
            if (null === $url && preg_match('{Development Server \((http://[^)]+)\) started}', $bytes, $matches)) {
                $url = $matches[1];
                io()->writeln("URL: {$url}");
            }
        },
    );

    fs()->remove($directory);
}
```

The port `0` lets the system pick a free port. `PHP_CLI_SERVER_WORKERS` is
important: without it, a slow request blocks all the others. It is not available
on Windows.

`withTrappedSignals()` needs the `pcntl` extension: without it, CTRL+C stops the
task too, and the temporary directory stays on the disk.

> [!WARNING]
> The built-in web server is meant for local use. Keep it on `127.0.0.1`, and
> protect the pages that trigger actions: any process of the machine, or any web
> page opened in a browser, can reach it.
