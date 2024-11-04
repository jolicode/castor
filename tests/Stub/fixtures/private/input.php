<?php

namespace Test\Private;

final class Foo
{
    public const PUBLIC = 'public';
    protected const PROTECTED = 'protected';
    private const PRIVATE = 'private';

    public ?string $public = null;
    protected ?string $protected = null;
    private ?string $private = null;

    public function __construct(
        private string $publicCpp,
        private string $protectedCpp,
        private string $privateCpp,
    ) {
    }

    public function publicMethod(): void
    {
        $this->protectedMethod();
    }

    protected function protectedMethod(): void
    {
        $this->privateMethod();
    }

    private function privateMethod(): void
    {
        // lorem ipsum
    }
}
