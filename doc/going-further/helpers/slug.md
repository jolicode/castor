# Slug

Castor provides a `slug()` function to slugify strings for use in URLs or filenames.
It uses the [AsciiSlugger from Symfony's String component](https://symfony.com/doc/current/string.html#slugger)
and provides the same signature:

```php
{% include "/examples/basic/slug/slug.php" start="<?php\n\nnamespace slug;\n\n" %}
```

> [!NOTE]
> The binary version of Castor does not include the `intl` extension. As a result,
> the `slug()` function in that version may not handle certain special characters.
