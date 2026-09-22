<?php

namespace Castor\Llm;

use Castor\Exception\LlmException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What llm() does: a mode, a known CLI with its options, or a custom command.
 *
 * It is parsed from the value of the "cli" argument, of the CASTOR_LLM
 * environment variable, or of the configuration.
 *
 * @internal
 */
#[Exclude]
final readonly class Selection
{
    public const MODE_ASK = 'ask';
    public const MODE_AUTO = 'auto';
    public const MODE_CHOOSE = 'choose';

    public const MODES = [self::MODE_ASK, self::MODE_AUTO, self::MODE_CHOOSE];

    /**
     * @param list<string>             $extraArguments
     * @param string|list<string>|null $customCommand
     */
    private function __construct(
        public ?string $mode = null,
        public ?Cli $cli = null,
        public ?string $model = null,
        public ?string $agent = null,
        public array $extraArguments = [],
        public string|array|null $customCommand = null,
    ) {
    }

    public static function mode(string $mode): self
    {
        if (!\in_array($mode, self::MODES, true)) {
            throw new LlmException(\sprintf('Unknown LLM mode "%s", expected one of "%s".', $mode, implode('", "', self::MODES)));
        }

        return new self(mode: $mode);
    }

    /**
     * @param list<string> $extraArguments
     */
    public static function forCli(Cli $cli, ?string $model = null, ?string $agent = null, array $extraArguments = []): self
    {
        if (null !== $model && !$cli->supportsModel()) {
            throw new LlmException(\sprintf('The "%s" LLM CLI does not support choosing the model.', $cli->name));
        }

        if (null !== $agent && !$cli->supportsAgent()) {
            throw new LlmException(\sprintf('The "%s" LLM CLI does not support choosing the agent.', $cli->name));
        }

        return new self(cli: $cli, model: $model, agent: $agent, extraArguments: $extraArguments);
    }

    /**
     * @param string|list<string> $command
     */
    public static function forCustomCommand(string|array $command): self
    {
        if (\is_string($command) ? '' === trim($command) : !$command) {
            throw new LlmException('The LLM command cannot be empty.');
        }

        return new self(customCommand: $command);
    }

    /**
     * Parses a value: a mode ("ask", "auto", "choose"), the name of a known CLI
     * followed by its options ("claude --model opus"), or a custom command.
     *
     * @param string|list<string> $value
     */
    public static function fromValue(string|array $value, CliRegistry $clis): self
    {
        if (\is_string($value)) {
            $value = trim($value);
            $tokens = '' === $value ? [] : (preg_split('/\s+/', $value) ?: []);
        } else {
            $tokens = $value;
        }

        if (!$tokens) {
            throw new LlmException('The LLM value cannot be empty.');
        }

        if (1 === \count($tokens) && \in_array($tokens[0], self::MODES, true)) {
            return self::mode($tokens[0]);
        }

        $cli = $clis->get($tokens[0]);

        if (null === $cli) {
            return self::forCustomCommand($value);
        }

        $model = null;
        $agent = null;
        $extraArguments = [];

        for ($i = 1, $count = \count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];

            foreach (['--model' => &$model, '--agent' => &$agent] as $option => &$target) {
                if ($token === $option) {
                    $target = $tokens[++$i] ?? throw new LlmException(\sprintf('The "%s" option needs a value, in the LLM value "%s".', $option, \is_string($value) ? $value : implode(' ', $value)));

                    continue 2;
                }

                if (str_starts_with($token, $option . '=')) {
                    $target = substr($token, \strlen($option) + 1);

                    continue 2;
                }
            }
            unset($target);

            $extraArguments[] = $token;
        }

        return self::forCli($cli, $model, $agent, $extraArguments);
    }

    public function isMode(string $mode): bool
    {
        return $mode === $this->mode;
    }

    public function isFixed(): bool
    {
        return null === $this->mode;
    }

    /**
     * The canonical value, as accepted by fromValue().
     */
    public function toValue(): string
    {
        if (null !== $this->mode) {
            return $this->mode;
        }

        if (null !== $this->cli) {
            $tokens = [$this->cli->name];

            if (null !== $this->model) {
                $tokens[] = '--model';
                $tokens[] = $this->model;
            }

            if (null !== $this->agent) {
                $tokens[] = '--agent';
                $tokens[] = $this->agent;
            }

            return implode(' ', [...$tokens, ...$this->extraArguments]);
        }

        return \is_array($this->customCommand) ? implode(' ', $this->customCommand) : (string) $this->customCommand;
    }

    /**
     * A short name for the messages: the name of the CLI, or the custom command.
     */
    public function getName(): string
    {
        return null !== $this->cli ? $this->cli->name : $this->toValue();
    }

    /**
     * What Castor does, to complete a sentence like "Castor will ...".
     */
    public function describe(): string
    {
        return match ($this->mode) {
            self::MODE_ASK => 'ask for a confirmation before using the first LLM CLI installed',
            self::MODE_AUTO => 'use the first LLM CLI installed, without asking',
            self::MODE_CHOOSE => 'let you choose the LLM CLI at each run',
            default => null !== $this->cli
                ? \sprintf('always use the "%s" LLM CLI%s%s%s', $this->cli->name, null !== $this->model ? \sprintf(' with the model "%s"', $this->model) : '', null !== $this->agent ? \sprintf(' with the agent "%s"', $this->agent) : '', $this->extraArguments ? \sprintf(' with the arguments "%s"', implode(' ', $this->extraArguments)) : '')
                : \sprintf('always run the command "%s"', $this->toValue()),
        };
    }
}
