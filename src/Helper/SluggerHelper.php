<?php

namespace Castor\Helper;

use Symfony\Component\String\Slugger\AsciiSlugger;

use function Symfony\Component\String\u;

/** @internal */
final class SluggerHelper
{
    private static AsciiSlugger $slugger;

    public static function slug(string $string): string
    {
        self::$slugger ??= new AsciiSlugger();

        $string = u($string)->snake()->toString();

        return self::$slugger->slug($string)->lower()->toString();
    }
}
