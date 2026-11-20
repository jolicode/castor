---
description: >
  Learn how to start the built-in web server of PHP from a task with the
  `serve_php()` function, even in a compiled application.
---

# Serving a web interface

This document explains how to start a web server from a task.

## The `serve_php()` function

The `serve_php()` function serves a directory with the [built-in web
server](https://www.php.net/manual/en/features.commandline.webserver.php) of PHP,
until you stop it with CTRL+C. The system picks a free port, and Castor prints
the URL:

```php
#[AsTask()]
function web(): void
{
    serve_php(__DIR__ . '/web');
}
```

To do something else with the URL, pass a callback. It replaces the message, and
runs once the server is started:

```php
#[AsTask()]
function web(): void
{
    serve_php(__DIR__ . '/web', open(...));
}
```

The server runs 4 workers, so a slow request does not block the others. Windows
does not support them.

> [!WARNING]
> The built-in web server is meant for local use. It only listens on
> `127.0.0.1`, but protect the pages that trigger actions: any process of the
> machine, or any web page opened in a browser, can reach it.

## In a phar or a static binary

A web server cannot serve a directory from inside a phar: Castor copies it to a
temporary directory, and removes it when the server stops. Use `__DIR__` to give
the path of a directory of your application.

A static binary does not include the web server of PHP, so Castor uses the `php`
of the `PATH`. To run on a machine without PHP, [compile your application with a
PHP CLI](../extending-castor/compile.md#running-a-web-server).
