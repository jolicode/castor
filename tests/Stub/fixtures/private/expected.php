<?php

namespace Test\Private;

final class Foo
{
    public const PUBLIC = 'public';
    protected const PROTECTED = 'protected';
    public ?string $public = null;
    protected ?string $protected = null;
    public function __construct(private string $publicCpp, private string $protectedCpp, private string $privateCpp)
    {
    }
    public function publicMethod(): void
    {
    }
    protected function protectedMethod(): void
    {
    }
}
