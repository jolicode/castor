<?php

namespace Test\Foobar;

/**
 * @param int-mask-of<\Symfony\Component\Yaml\Yaml::PARSE_*> $flags
 * @param self::FOO|Unknown::BAR     $other
 */
function yaml_parse(string $content, int $flags = 0, string $other = ''): mixed
{
}
