<?php

namespace Castor\Runner\Parallel;

/**
 * Aligns the names of the jobs in a column, as wide as the longest name.
 *
 * @internal
 */
final readonly class JobNameColumn
{
    // A longer name would push the rest of the line out of the screen
    private const MAX_WIDTH = 30;

    private int $width;

    /**
     * @param non-empty-list<string> $names
     */
    public function __construct(array $names)
    {
        $this->width = min(self::MAX_WIDTH, max(array_map(mb_strwidth(...), $names)));
    }

    /**
     * @param string $before Written before the name, like an opening bracket
     * @param string $after  Written after the name, like a closing bracket
     */
    public function format(string $name, string $before = '', string $after = ''): string
    {
        $text = $before . mb_strimwidth($name, 0, $this->width, '…') . $after;

        return $text . str_repeat(' ', $this->width + mb_strwidth($before . $after) - mb_strwidth($text));
    }
}
