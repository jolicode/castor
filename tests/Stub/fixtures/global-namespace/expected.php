<?php

namespace Symfony\Component\String {
    if (!\function_exists(\Symfony\Component\String\u::class)) {
        function u(?string $string = ''): \Symfony\Component\String\UnicodeString
        {
        }
    }
    if (!\function_exists(\Symfony\Component\String\b::class)) {
        function b(?string $string = ''): \Symfony\Component\String\ByteString
        {
        }
    }
    if (!\function_exists(\Symfony\Component\String\s::class)) {
        /**
         * @return \Symfony\Component\String\UnicodeString|\Symfony\Component\String\ByteString
         */
        function s(?string $string = ''): \Symfony\Component\String\AbstractString
        {
        }
    }
}
namespace {
    if (!\function_exists('dump')) {
        function dump(mixed ...$vars): mixed
        {
        }
    }
    if (!\function_exists('dd')) {
        function dd(mixed ...$vars): never
        {
        }
    }
}
