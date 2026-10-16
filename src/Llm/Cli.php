<?php

namespace Castor\Llm;

use Castor\Exception\LlmException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A LLM CLI Castor knows how to run non-interactively.
 *
 * @internal
 */
#[Exclude]
final readonly class Cli
{
    public const PROMPT_PLACEHOLDER = '{prompt}';
    public const OUTPUT_FILE_PLACEHOLDER = '{output_file}';
    public const OPTIONS_PLACEHOLDER = '{options}';

    /**
     * The read-only arguments and agent are kept apart from the command: they
     * are what makes llm_ask() read-only, whatever the CLI.
     *
     * @param list<string> $command           The command, where "{options}" is replaced by the read-only
     *                                        arguments, the model, the agent and the extra arguments. The
     *                                        prompt is written on the standard input of the CLI, unless the
     *                                        command has a "{prompt}" argument, and the answer is read on its
     *                                        standard output, unless the command has an "{output_file}" argument
     * @param list<string> $readOnlyArguments The arguments that let the model read the project to answer, but
     *                                        neither modify it nor run commands, when the CLI supports it
     * @param ?string      $modelOption       The option choosing the model, when the CLI supports it
     * @param ?string      $agentOption       The option choosing the agent, when the CLI supports it
     * @param ?string      $readOnlyAgent     The agent making the CLI read-only, used when none is chosen
     * @param list<string> $models            Some models the CLI supports, proposed by the interactive selection
     * @param bool         $mayModifyProject  Whether the model may modify the project or run commands, whatever
     *                                        the arguments Castor gives to the CLI: Castor does not propose it
     *                                        by default
     */
    public function __construct(
        public string $name,
        public array $command,
        public array $readOnlyArguments = [],
        public ?string $modelOption = null,
        public ?string $agentOption = null,
        public ?string $readOnlyAgent = null,
        public array $models = [],
        public bool $mayModifyProject = false,
    ) {
    }

    public function getBinary(): string
    {
        return $this->command[0];
    }

    public function supportsModel(): bool
    {
        return null !== $this->modelOption;
    }

    public function supportsAgent(): bool
    {
        return null !== $this->agentOption;
    }

    /**
     * The command run by llm_ask(), in the read-only mode of the CLI when it has one.
     *
     * @param list<string> $extraArguments
     *
     * @return list<string> The command, still with the "{prompt}" and "{output_file}" placeholders
     */
    public function buildReadOnlyCommand(?string $model = null, ?string $agent = null, array $extraArguments = []): array
    {
        $options = $this->readOnlyArguments;

        if (null !== $model) {
            if (null === $this->modelOption) {
                throw new LlmException(\sprintf('The "%s" LLM CLI does not support choosing the model.', $this->name));
            }

            $options[] = $this->modelOption;
            $options[] = $model;
        }

        if (null !== $agent && null === $this->agentOption) {
            throw new LlmException(\sprintf('The "%s" LLM CLI does not support choosing the agent.', $this->name));
        }

        $agent ??= $this->readOnlyAgent;

        if (null !== $agent && null !== $this->agentOption) {
            $options[] = $this->agentOption;
            $options[] = $agent;
        }

        $options = [...$options, ...$extraArguments];

        $command = [];

        foreach ($this->command as $part) {
            if (self::OPTIONS_PLACEHOLDER === $part) {
                array_push($command, ...$options);
            } else {
                $command[] = $part;
            }
        }

        return $command;
    }
}
