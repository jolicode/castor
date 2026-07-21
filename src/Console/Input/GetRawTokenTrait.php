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
    private function getRawTokens(InputInterface $input, bool $onlyAfterEndOption): array
    {
        if (!$input instanceof ArgvInput) {
            throw new \RuntimeException('The input must be an instance of ArgvInput.');
        }

        $tokens = $this->getRawArgvTokens($input);

        $parameters = [];
        $keep = false;
        $delimiterFound = false;

        foreach ($tokens as $value) {
            if (!$keep && !$onlyAfterEndOption && $value === $input->getFirstArgument()) {
                $keep = true;

                continue;
            }

            if ('--' === $value && !$delimiterFound) {
                $delimiterFound = true;

                if ($onlyAfterEndOption) {
                    $keep = true;
                }

                continue;
            }

            if ($keep) {
                $parameters[] = $value;
            }
        }

        return $parameters;
    }

    /**
     * @return list<string>
     */
    private function getRawArgvTokens(ArgvInput $input): array
    {
        // @phpstan-ignore-next-line
        return (fn () => $this->tokens)->bindTo($input, ArgvInput::class)();
    }
}
