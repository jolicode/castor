---
description: Recommended installation method of Castor.
---

# The official installer

This document outlines the recommended and easiest way to install Castor
on your system using the official installer.

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

### --static {: #static }

If you don't have PHP available on your system, Castor can be installed with a
static binary that embeds PHP, so it can be run anywhere.

Use the `--static` option to install Castor this way:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --static
```

### --install-dir {: #install-dir }

By default, the installer will install Castor in the current user's
`$HOME/.local/bin` directory.

You can change that by using the `--install-dir` option:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --install-dir /usr/local/bin
```

### --version {: #version }

By default, the installer will install the latest version of Castor.

You can install a specific version of Castor by using the `--version` option:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --version=v1.0.0
```

## Updating Castor

If you installed Castor using the installer (phar or static binary), you can
update it to the latest version using the `self-update` command:

```bash
castor self-update
```

### Self-update options

- `--force` or `-f`: Force update even if already up to date
- `--no-backup`: Skip creating a backup of the current binary
- `--rollback` or `-r`: Rollback to the previous version

When the [GitHub CLI](https://cli.github.com/) is installed and authenticated,
the installer and `self-update` also verify the provenance of the downloaded
binary: they check, with `gh attestation verify`, that the binary was built by
Castor's own GitHub Actions workflow.

> [!NOTE]
> The `self-update` command is not available for source installations or Composer
> project dependencies. For global Composer installs (`composer global require`),
> `self-update` runs `composer global update` under the hood. For project
> dependencies, use `composer update jolicode/castor` instead.

## Snapshot builds

Every push on the `main` branch publishes a `snapshot` pre-release, so you can
try what is coming next before it is released. Install it with:

```bash
curl "https://castor.jolicode.com/install" | bash -s -- --version=snapshot
```

or, from an existing installation:

```bash
castor self-update --snapshot
```

A snapshot reports a version like `v1.7.0-14-g4531440`: the last release, the
number of commits since, and the commit it was built from. To go back to the
latest release, run `castor self-update` without `--snapshot`.

## Other installation methods

If you cannot use the installer, see
[the alternative methods documentation](alternative-methods.md).
