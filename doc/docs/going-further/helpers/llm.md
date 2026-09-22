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
runs them in their non-interactive mode. Castor knows the following CLIs, and
tries them in this order:

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

The first installed CLI that answers is used: a CLI that fails, because it is
not logged in for instance, is skipped with a warning and the next one is
tried. Once a CLI has answered, Castor keeps using it for the next questions of
the same run.

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

Use the `cli` argument to choose the CLI, by its name:

```php
use function Castor\llm;

$summary = llm('Summarize this text: ...', cli: 'codex');
```

You can also pass any command, as a string or an array, to use a CLI Castor
does not know, or to pass extra options to a known one. The prompt is written
on the standard input of the command, and its standard output is the answer:

```php
use function Castor\llm;

$summary = llm('Summarize this text: ...', cli: ['ollama', 'run', '--nowordwrap', 'llama3.2']);
$summary = llm('Summarize this text: ...', cli: ['claude', '--print', '--model', 'haiku']);
```

When the command is an array, an argument equal to `{prompt}` is replaced by
the prompt, for the CLIs that do not read it on their standard input:

```php
use function Castor\llm;

$summary = llm('Summarize this text: ...', cli: ['gemini', '--model', 'gemini-2.5-flash', '--prompt', '{prompt}']);
```

Finally, the `CASTOR_LLM_CLI` environment variable chooses the CLI for the
tasks that do not choose one themselves. It takes the name of a known CLI, or a
command line which receives the prompt on its standard input:

```bash
CASTOR_LLM_CLI=codex castor llm:summarize-commits
CASTOR_LLM_CLI="ollama run --nowordwrap llama3.2" castor llm:summarize-commits
```

## Errors

The `llm()` function throws a `Castor\Exception\LlmException` when no
known CLI is installed, when the chosen CLI is not installed or fails, or when
none of the installed CLIs could answer. The message of the exception contains
the error output of the CLIs, to help you fix their setup.
