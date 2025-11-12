---
description: Recommended installation method of Castor.
---

# The official installer

## Using the installer

The installer is the recommended and easiest way to install Castor on your system.
It works on Linux and macOS.

```bash
curl "https://castor.jolicode.com/install" | bash
```

> [!NOTE]
> This method will install a phar and thus, it requires PHP >= 8.2 installed on
> your system.
>
> See the `--static` option to install a static binary that does not require PHP
> installed.

### Options

### --static

If you don't have PHP available on your system, Castor can be installed with a
static binary that embeds PHP, so it can be run anywhere.

Use the `--static` option to install Castor this way:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --static
```

### --install-dir

By default, the installer will install Castor in the current user's
`$HOME/.local/bin` directory.

You can change that by using the `--install-dir` option:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --install-dir /usr/local/bin
```

### --version

By default, the installer will install the latest version of Castor.

You can install a specific version of Castor by using the `--version` option:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --version=v1.0.0
```

## Other installation methods

If you cannot use the installer, see
[the alternative methods documentation](alternative-methods.md).
