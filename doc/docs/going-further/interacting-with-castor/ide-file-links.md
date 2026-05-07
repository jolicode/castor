---
description: >
  Learn how to configure Castor to display clickable file links in task help
  text, opening the task definition directly in your IDE.
---

# IDE File Links

When you run `castor help <task-name>`, Castor displays the path to the file
where the task is defined, along with the line number. This path is rendered as
a clickable link that can open directly in your IDE.

```console
$ castor help my-task

Description:
  My task description

Usage:
  my-task [options]

Help:
  Defined in /path/to/castor.php:12
```

## Configuration

Castor relies on the `SYMFONY_IDE` environment variable or the
`xdebug.file_link_format` PHP ini option to determine the link format.

### Via environment variable

Set the `SYMFONY_IDE` environment variable to the name of your IDE or to a
custom URL format:

```bash
# Using a short alias
export SYMFONY_IDE=phpstorm

# Using a custom format
export SYMFONY_IDE="phpstorm://open?file=%f&line=%l"
```

The supported short aliases are:

| Alias | IDE |
|---|---|
| `phpstorm` | PhpStorm |
| `vscode` | Visual Studio Code |
| `sublime` | Sublime Text |
| `atom` | Atom |
| `textmate` | TextMate |
| `macvim` | MacVim |
| `emacs` | Emacs |

### Via `php.ini`

You can also set the `xdebug.file_link_format` option in your `php.ini`
configuration file. The format is identical to the custom URL format above:

```ini
; PhpStorm
xdebug.file_link_format="phpstorm://open?file=%f&line=%l"

; PhpStorm with JetBrains Toolbox
xdebug.file_link_format="jetbrains://phpstorm/navigate/reference?project=example&path=%f:%l"

; Sublime Text
xdebug.file_link_format="subl://open?url=file://%f&line=%l"

; Visual Studio Code
xdebug.file_link_format="vscode://file/%f:%l"
```

In the format string, `%f` is replaced by the file path and `%l` by the line
number.

> [!NOTE]
> If neither `SYMFONY_IDE` nor `xdebug.file_link_format` is configured, the
> link falls back to a plain `file://` URI.
