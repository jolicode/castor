---
description: >
  Learn how to ask a question to a LLM from a Castor task with the `llm()`
  function, through the AI CLI already installed and logged in on your machine.
---

# Asking a LLM

## The `llm()` function

Castor provides an `llm()` function to ask a question to a Large Language
Model and get its answer as a string:

```php
{% include "/examples/basic/llm/llm.php" start="<?php\n\nnamespace llm;\n\n" %}
```

Castor does not talk to any AI provider itself: it relies on the AI command
line tools you may already have installed and logged in on your machine, and
runs them in their non-interactive mode. Castor knows the following CLIs:

- [Claude Code](https://claude.com/product/claude-code) (`claude`)
- [Codex CLI](https://developers.openai.com/codex/cli) (`codex`)
- [OpenCode](https://opencode.ai) (`opencode`)
- [Gemini CLI](https://geminicli.com) (`gemini`)
- [GitHub Copilot CLI](https://github.com/features/copilot/cli) (`copilot`)
- [Cursor CLI](https://cursor.com/cli) (`cursor-agent`)
- [Amp](https://ampcode.com) (`amp`)
- [Crush](https://github.com/charmbracelet/crush) (`crush`)
- [Qwen Code](https://github.com/QwenLM/qwen-code) (`qwen`)
- [llm](https://llm.datasette.io) (`llm`)
- [Mods](https://github.com/charmbracelet/mods) (`mods`)

By default, the first time a task asks a LLM, Castor shows the CLI it is about
to use and the beginning of the prompt, and asks for a confirmation. It then
uses the first installed CLI that answers, for the whole run. The next section
explains how to choose the CLI, and how to skip the confirmation.

> [!NOTE]
> The CLI runs in the working directory of the current context, with its own
> configuration, as when you use it directly: depending on the CLI, the model
> may read the files of the project to answer. When the CLI has a read-only
> mode, Castor uses it so that the model never modifies the project nor runs
> commands: Claude Code runs without any tool, Codex in its read-only sandbox,
> OpenCode with its `plan` agent, Gemini CLI and Qwen Code in their `plan`
> approval mode. The other CLIs keep their own behavior for a non-interactive
> run, where the actions requiring an approval are usually denied.

## Choosing the CLI

What Castor does when a task asks a LLM is described by a value, which is one
of:

- `ask`: the default. Castor asks for a confirmation the first time a task asks
  a LLM, then uses the first installed CLI that answers.
- `auto`: Castor uses the first installed CLI that answers, without asking. A
  CLI that fails, because it is not logged in for instance, is skipped with a
  warning and the next one is tried. Once a CLI has answered, Castor keeps using
  it for the next questions of the run.
- `choose`: the first time a task asks a LLM, Castor lets you choose the CLI
  among the installed ones, then its model and its agent when the CLI supports
  them. The choice is kept for the run.
- the name of a known CLI, like `claude` or `codex`, to always use it. It can
  be followed by the `--model` and `--agent` options, that Castor translates
  for the CLI, and by any other arguments, passed to the CLI as is:
  `codex --model gpt-5.5`, `opencode --agent build`, `claude --effort high`...
- any other command, to use a CLI Castor does not know: `ollama run llama3.2`.
  The prompt is written on the standard input of the command, and its standard
  output is the answer.

Castor takes the value from the first of these sources that defines one:

1. the `cli` argument of the `llm()` function;
2. the `CASTOR_LLM` environment variable;
3. the configuration of the project, made with the `castor:llm:configure`
   command;
4. the global configuration, made with the `castor:llm:configure --global`
   command;
5. the default, `ask`.

> [!NOTE]
> The `ask` and `choose` values need someone to answer: in a non-interactive
> run, like in CI or with the `--no-interaction` option, the task fails with an
> error explaining how to configure Castor. Set the `CASTOR_LLM` environment
> variable to `auto` or to a CLI in such environments.

### The `cli` argument

The `cli` argument of the `llm()` function takes a value, as a string or as an
array:

```php
use function Castor\llm;

$summary = llm('Summarize this text: ...', cli: 'codex');
$summary = llm('Summarize this text: ...', cli: 'claude --model opus');
$summary = llm('Summarize this text: ...', cli: ['ollama', 'run', '--nowordwrap', 'llama3.2']);
```

When the value is an array, an argument equal to `{prompt}` is replaced by the
prompt, for the custom commands that do not read it on their standard input:

```php
use function Castor\llm;

$summary = llm('Summarize this text: ...', cli: ['my-llm', '--prompt', '{prompt}']);
```

Since the task chooses the CLI, Castor does not ask for a confirmation.

### The `CASTOR_LLM` environment variable

The `CASTOR_LLM` environment variable takes a value, for a single run or for
the environments where nobody can answer a question:

```bash
CASTOR_LLM=auto castor llm:summarize-commits
CASTOR_LLM="claude --model opus" castor llm:summarize-commits
CASTOR_LLM="ollama run --nowordwrap llama3.2" castor llm:summarize-commits
```

### The `castor:llm:configure` command

The `castor:llm:configure` command saves a value, for the current project or,
with the `--global` option, for all your projects. Without argument, it asks
what to do, then lets you choose the CLI, its model and its agent:

```bash
castor castor:llm:configure
castor castor:llm:configure --global
```

The value can also be passed as an argument, and removed with the `--reset`
option:

```bash
castor castor:llm:configure auto
castor castor:llm:configure "claude --model opus" --global
castor castor:llm:configure --reset
```

The configuration is a preference of yours on this machine, not something to
share with the project: it is stored in the
[cache directory of Castor](cache.md#cache-location-on-the-filesystem), and
removing the cache resets it.

### The `castor:llm:debug` command

The `castor:llm:debug` command shows which CLIs are installed, the value of
each source and the one Castor uses, and the exact command it runs. With the
`--test` option, it also sends a prompt to check the CLI answers:

```bash
castor castor:llm:debug
castor castor:llm:debug --test
```

## Errors

The `llm()` function throws a `Castor\Exception\LlmException` when no known
CLI is installed, when the chosen CLI is not installed or fails, when none of
the installed CLIs could answer, or when the confirmation is refused or cannot
be asked. The message of the exception contains the error output of the CLIs,
to help you fix their setup.
