<?php

namespace Castor\Helper;

use Symfony\Component\String\Slugger\AsciiSlugger;

use function Symfony\Component\String\u;

/** @internal */
final readonly class Slugger
{
    public function __construct(
        private AsciiSlugger $slugger,
    ) {
    }

    public function slug(string $string): string
    {
        $string = u($string)->snake()->toString();

        return $this->slugger->slug($string)->lower()->toString();
    }
}
