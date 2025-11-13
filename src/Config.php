<?php

namespace Castor;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Each flag supports three states:
 * - true: explicitly enabled
 * - false: explicitly disabled
 * - null: not configured (will show deprecation warning and use default)
 */
#[Exclude]
class Config
{
    /** @var array<string, bool|null> */
    private array $flags = [];

    /** @var array<string, bool> */
    private static array $warnedFlags = [];

    public function isEnabled(ConfigFlag $flag): bool
    {
        $value = $this->get($flag);

        $futureDefault = !$flag->defaultValueWhenNull();

        if (null === $value || $value !== $futureDefault) {
            $this->triggerDeprecationWarning($flag, $value);
        }

        if (\is_bool($value)) {
            return $value;
        }

        return $flag->defaultValueWhenNull();
    }

    public function withEnabled(ConfigFlag ...$flags): self
    {
        foreach ($flags as $flag) {
            $this->set($flag, true);
        }

        return $this;
    }

    public function withDisabled(ConfigFlag ...$flags): self
    {
        foreach ($flags as $flag) {
            $this->set($flag, false);
        }

        return $this;
    }

    /**
     * @return array<string, bool|null>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    private function get(ConfigFlag $flag): ?bool
    {
        return $this->flags[$flag->name] ?? null;
    }

    private function set(ConfigFlag $flag, ?bool $value): void
    {
        $this->flags[$flag->name] = $value;
    }

    private function triggerDeprecationWarning(ConfigFlag $flag, ?bool $value): void
    {
        // Only warn once per flag per execution
        if (isset(self::$warnedFlags[$flag->name])) {
            return;
        }

        self::$warnedFlags[$flag->name] = true;

        $currentDefault = $flag->defaultValueWhenNull() ? 'true' : 'false';
        $futureDefault = !$flag->defaultValueWhenNull() ? 'true' : 'false';
        $futureVersion = $flag->willBeDefaultInVersion();

        if (null === $value) {
            $message = \sprintf(
                'Configuration flag "%s" is not set and defaults to "%s". It will default to "%s" in version %s. ',
                $flag->name,
                $currentDefault,
                $futureDefault,
                $futureVersion,
            );
        } else {
            $currentValue = $value ? 'true' : 'false';
            $message = \sprintf(
                'Configuration flag "%s" is explicitly set to "%s", but will default to "%s" in version %s. ',
                $flag->name,
                $currentValue,
                $futureDefault,
                $futureVersion,
            );
        }

        trigger_deprecation('castor/castor', $futureVersion, $message);
    }
}
