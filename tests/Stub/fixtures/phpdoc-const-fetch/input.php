<?php

namespace Test\Foobar;

use Symfony\Component\Yaml\Yaml;

/**
 * @param int-mask-of<Yaml::PARSE_*> $flags
 * @param self::FOO|Unknown::BAR     $other
 */
function yaml_parse(string $content, int $flags = 0, string $other = ''): mixed
{
    return Yaml::parse($content, $flags);
}
