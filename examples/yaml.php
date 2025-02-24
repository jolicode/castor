<?php

namespace yaml;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\yaml_dump;
use function Castor\yaml_parse;

#[AsTask(description: 'Parse a YAML content')]
function parse(): void
{
    $data = yaml_parse(<<<'YAML'
        foo: bar
        YAML);
    io()->writeln($data['foo']);
}

#[AsTask(description: 'Dump a YAML content')]
function dump(): void
{
    $data = ['foo' => 'bar'];
    io()->writeln(yaml_dump($data));
}
