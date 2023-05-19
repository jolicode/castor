<?php

namespace Castor;

use Symfony\Component\String\Slugger\AsciiSlugger;

class SluggerHelper
{
    private static AsciiSlugger $slugger;

    public static function slug(string $string): string
    {
        self::$slugger ??= new AsciiSlugger();

        return self::$slugger->slug($string)->lower()->toString();
    }
}
