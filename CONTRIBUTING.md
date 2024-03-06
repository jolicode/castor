# Contributing

First of all, **thank you** for contributing, **you are awesome**!

Everybody should be able to help. Here's how you can do it:

1. [Fork it](https://github.com/jolicode/castor/fork_select)
2. Improve it
3. Make sure [docs](#update-the-documentation) and [changelog](#update-the-changelog) are up-to-date
4. Make sure [tests](#tests), [coding standards](#coding-standards) checks and [static analysis](#static-analysis) checks are up-to-date and green
5. Submit a [pull request](https://help.github.com/articles/creating-a-pull-request)

Here's some tips to make you the best contributor ever:

* [Rules](#rules)
* [Keeping your fork up-to-date](#keeping-your-fork-up-to-date)

## Rules

Here are a few rules to follow in order to ease code reviews, and discussions
before maintainers accept and merge your work.

* You MUST follow the [PSR-1](http://www.php-fig.org/psr/1/) and
[PSR-12](http://www.php-fig.org/psr/12/) (see [Coding Standards](#coding-standards)).
* You MUST run the test suite (see [Tests](#tests)).
* You MUST write (or update) tests.
* You SHOULD write documentation.

Please, write [commit messages that make
sense](http://tbaggery.com/2008/04/19/a-note-about-git-commit-messages.html),
and [rebase your branch](http://git-scm.com/book/en/Git-Branching-Rebasing)
before submitting your Pull Request (see also how to [keep your
fork up-to-date](#keeping-your-fork-up-to-date)).

One may ask you to [squash your
commits](http://gitready.com/advanced/2009/02/10/squashing-commits-with-rebase.html)
too. This is used to "clean" your Pull Request before merging it (we don't want
commits such as `fix tests`, `fix 2`, `fix 3`, etc.).

Also, while creating your Pull Request on GitHub, you MUST write a description
which gives the context and/or explains why you are creating it.

Your work will then be reviewed as soon as possible (suggestions about some
changes, improvements or alternatives may be given).

## Tests

Run the tests using the following script:

```shell
vendor/bin/simple-phpunit
```

Keep in mind tests will also run on CI covering all the different Castor
supported PHP versions and the lowest PHP version with lowest dependencies version.

So it could be green on your local php version but fail on CI.

### Testing functions

When your changes are related to Castor's provided functions, run the `bin/generate-tests.php` 
to generate tests that would reflect your changes.

## Coding Standards

Set up [PHP CS fixer](http://cs.sensiolabs.org/) in the [tools/php-cs-fixer](tools/php-cs-fixer) directory

```shell
# from castor project root dir
composer install --working-dir=tools/php-cs-fixer
```

And run the tool to make your code compliant with
castor's coding standards:

```shell
tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
```

## Static Analysis

Set up [PHPStan](https://phpstan.org/) in the [tools/phpstan](tools/phpstan) directory

```shell
# from castor project root dir
composer install --working-dir=tools/phpstan
```

And run the tool to make your code compliant with
castor's static analysis checks:

```shell
tools/phpstan/vendor/bin/phpstan --configuration=phpstan.neon
```

## Update the Documentation

When introducing non-internal code that user will be able to rely on in their
own project, like new Castor functions for example, make sure to document its usage.

## Update the Changelog

Add a new entry in [CHANGELOG.md](CHANGELOG.md) summarizing your changes.
Multiple points for a single PR is fine. 

Prefix with `[BC Break]` entries that 
are related to backward compatibility breaking changes.

## Keeping your fork up-to-date

To keep your fork up-to-date, you should track the upstream (original) one
using the following command:


```shell
git remote add upstream https://github.com/jolicode/castor.git
```

Then get the upstream changes:

```shell
git checkout main
git pull --rebase origin main
git pull --rebase upstream main
git checkout <your-branch>
git rebase main
```

Finally, publish your changes:

```shell
git push -f origin <your-branch>
```

Your pull request will be automatically updated.

Thank you!
