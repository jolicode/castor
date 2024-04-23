<?php

namespace Castor\Console\Input;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

/** @internal */
trait GetRawTokenTrait
{
    /**
     * @return list<string>
     */
    private function getRawTokens(InputInterface $input): array
    {
        if (!$input instanceof ArgvInput) {
            throw new \RuntimeException('The input must be an instance of ArgvInput.');
        }

        // @phpstan-ignore-next-line
        $tokens = (fn () => $this->tokens)->bindTo($input, ArgvInput::class)();

        $parameters = [];
        $keep = false;
        foreach ($tokens as $value) {
            if (!$keep && $value === $input->getFirstArgument()) {
                $keep = true;

                continue;
            }
            if ($keep) {
                $parameters[] = $value;
            }
        }

        return $parameters;
    }
}
