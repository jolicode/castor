<?php

namespace yaml;

use Castor\Attribute\AsTask;

use function Castor\yaml_dump;
use function Castor\yaml_parse;

#[AsTask(description: 'Parse a YAML content')]
function parse(): void
{
    $data = yaml_parse(<<<'YAML'
foo: bar
YAML);
    echo $data['foo'] . "\n";
}

#[AsTask(description: 'Dump a YAML content')]
function dump(): void
{
    $data = ['foo' => 'bar'];
    echo yaml_dump($data) . "\n";
}
