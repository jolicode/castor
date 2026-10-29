<?php

namespace CastorTest\Functions;

function shout(string $message): string
{
    return strtoupper($message);
}
