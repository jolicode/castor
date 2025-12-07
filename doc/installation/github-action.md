---
description: How to set up Castor in your GitHub Actions workflows.
---

# GitHub Action

This document explains how to set up Castor within your GitHub Actions workflows.

## Using the official setup-castor action

Castor provides a [GitHub Action to install Castor in your workflow](https://github.com/marketplace/actions/setup-castor).

Here is an example:

```yaml
jobs:
  my-job:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v5

      - name: Setup castor
        uses: castor-php/setup-castor@v0.1.0

      - name: Run castor "hello" task
        run: castor hello
```

This action will use the static binary to install Castor in your workflow, so you will not
need to have PHP installed on the runner.

## Using setup-php action

If you need PHP, it can also be installed in a GitHub Action by using the action
`shivammathur/setup-php@v2` and specifying `castor` in the `tools` option.
This will configure PHP with the right version but also make castor available in
the next steps.

Here is an example:

```yaml
jobs:
  my-job:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v5

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: castor

      - name: Run castor "hello" task
        run: castor hello
```
