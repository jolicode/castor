---
description: >
  Learn how to ask a question to a LLM, or let it work on the project, from a
  Castor task with the `llm_ask()` and `llm_run()` functions, through the AI
  CLI already installed and logged in on your machine.
---

# Using a LLM

## The `llm_ask()` function

Castor provides an `llm_ask()` function to ask a question to a Large Language
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
- [Mistral Vibe](https://mistral.ai/products/vibe) (`vibe`)

By default, the first time a task asks a LLM, Castor shows the beginning of the
prompt and asks which installed CLI to use, then its model and its agent when
the CLI supports them, or lets you refuse to send the prompt. It then uses this
CLI for the whole run. The next section explains how to choose the CLI once and
for all, to skip these questions.

> [!NOTE]
> The CLI runs in the working directory of the current context, with its own
> configuration, as when you use it directly: depending on the CLI, the model
> may read the files of the project to answer. When the CLI has a read-only
> mode, Castor uses it so that the model never modifies the project nor runs
> commands: Claude Code runs without any tool, Codex in its read-only sandbox,
> OpenCode with an agent defined by Castor that may only read the files of the
> project, Mistral Vibe with its `plan` agent, Cursor CLI in its `ask` mode and
> Qwen Code in its `plan` approval mode. The other CLIs keep their own behavior
> for a non-interactive run, where the actions requiring an approval are
> usually denied. Choosing another agent for OpenCode or Mistral Vibe replaces
> this agent: what the model may do then depends on the agent you choose.
>
> Castor defines the agent of OpenCode in the `OPENCODE_CONFIG_CONTENT`
> environment variable, merged with the configuration you may already give in
> this variable: Castor can only merge a JSON object, without comments nor
> trailing commas. `llm_run()` gives this variable to OpenCode as it is.
>
> Amp and Crush approve all the actions of the model in a non-interactive run,
> and Gemini CLI lets the model leave its `plan` approval mode for the `yolo`
> one: Castor does not propose them by default.

## The `llm_run()` function

The `llm_run()` function lets the LLM work on the project instead of only
answering: the CLI is run in its non-interactive mode, allowed to modify the
files and to run commands, and its final answer is returned:

```php
{% include "/examples/basic/llm/run.php" start="<?php\n\nnamespace llm;\n\n" %}
```

The CLI does what the prompt asks with the autonomy it has when you tell it
not to ask: Claude Code accepts the edits and the commands, Codex runs in its
`workspace-write` sandbox with the network, Gemini CLI and Qwen Code in their
`yolo` approval mode, GitHub Copilot CLI with all its tools and URLs allowed,
OpenCode with its `build` agent, Cursor CLI, Amp, Crush and Mistral Vibe with
all the actions of their default agent approved. The `llm` CLI only answers
questions: it is refused, and not proposed. A custom command is run the same
way as for `llm_ask()`: it is up to you to make it able to work on the project.

An action the CLI refuses does not make it fail: Claude Code still refuses to
write in its protected paths, like `.git`, `.claude`, `.husky` or
`.pre-commit-config.yaml`, and to edit the files outside the working directory,
for instance. Check what the model did, with `git diff` for instance, before
trusting it.

The CLI is chosen the same way as for `llm_ask()`. By default, the first time
a task lets a LLM work on the project, Castor shows the beginning of the prompt
and asks which CLI to use, among the ones that can modify the project, or lets
you refuse: the CLI and the agent chosen for the questions may not be able to
work on the project, so `llm_run()` has its own choice, kept for the run. With
a CLI set in the configuration or in the `CASTOR_LLM` environment variable,
Castor asks nothing: the task decides.

A value set in the `cli` argument, in the `CASTOR_LLM` environment variable or
in the configuration is used by both functions, with its agent and its
arguments. An agent that suits one function may not suit the other: with the
`plan` agent of OpenCode, `llm_run()` cannot modify the project, and with its
`build` agent, `llm_ask()` can.

> [!NOTE]
> The sandbox of Codex keeps the `.git` directory read-only: the model cannot
> commit, nor finish a merge. Gemini CLI and OpenCode print the text of every
> step of their work, not only their final answer, so the returned string may
> be the whole narration. When the context sets a timeout, only the CLI is
> stopped after that time: the project may be left half-modified, and the
> commands the CLI started may keep running. Use a context without timeout for
> a long run.

## Choosing the CLI

How Castor chooses the CLI is described by a value, which is one of:

- `choose`: the default. The first time a task asks a LLM, Castor lets you
  choose the CLI among the installed ones, then its model and its agent when
  the CLI supports them. The choice is kept for the run. `llm_run()` asks for
  its own choice, among the CLIs that can modify the project.
- the name of a known CLI, like `claude` or `codex`, to always use it. It can
  be followed by the `--model` and `--agent` options, that Castor translates
  for the CLI, and by any other arguments, passed to the CLI as is:
  `codex --model gpt-5.5`, `claude --agent reviewer`, `claude --effort high`...
- any other command, to use a CLI Castor does not know: `ollama run llama3.2`.
  The prompt is written on the standard input of the command, and its standard
  output is the answer.

Castor takes the value from the first of these sources that defines one:

1. the `cli` argument of the `llm_ask()` and `llm_run()` functions;
2. the `CASTOR_LLM` environment variable;
3. the configuration of the project, made with the `castor:llm:configure`
   command;
4. the global configuration, made with the `castor:llm:configure --global`
   command;
5. the default, `choose`.

Castor uses the CLI it is given, and only it: when it fails, `llm_ask()` and
`llm_run()` throw an exception, and the prompt is not sent to another CLI.

> [!NOTE]
> The `choose` value needs someone to answer: in a non-interactive run, like in
> CI or with the `--no-interaction` option, the task fails with an error
> explaining how to configure Castor. Set the `CASTOR_LLM` environment variable
> to a CLI in such environments.
>
> The same goes for a job of [`parallel()`](parallel.md), since its output is
> captured: call `llm_ask()` once before `parallel()` to answer the question,
> the jobs then use the same CLI. The choice is kept for each function: for
> jobs that call `llm_run()`, call `llm_run()` before `parallel()`.

### The `cli` argument

The `cli` argument of the `llm_ask()` and `llm_run()` functions takes a value,
as a string or as an array:

```php
use function Castor\llm_ask;

$summary = llm_ask('Summarize this text: ...', cli: 'codex');
$summary = llm_ask('Summarize this text: ...', cli: 'claude --model opus');
$summary = llm_ask('Summarize this text: ...', cli: ['ollama', 'run', '--nowordwrap', 'llama3.2']);
```

When the value is an array, an argument equal to `{prompt}` is replaced by the
prompt, for the custom commands that do not read it on their standard input:

```php
use function Castor\llm_ask;

$summary = llm_ask('Summarize this text: ...', cli: ['my-llm', '--prompt', '{prompt}']);
```

Since the task chooses the CLI, Castor does not ask which one to use.

### The `CASTOR_LLM` environment variable

The `CASTOR_LLM` environment variable takes a value, for a single run or for
the environments where nobody can answer a question:

```bash
CASTOR_LLM=codex castor llm:summarize-commits
CASTOR_LLM="claude --model opus" castor llm:summarize-commits
CASTOR_LLM="ollama run --nowordwrap llama3.2" castor llm:summarize-commits
```

### The `castor:llm:configure` command

The `castor:llm:configure` command saves a value, for the current project or,
with the `--global` option, for all your projects. Without argument, it lets
you choose the CLI and its model, but not the agent, since the configuration is
used by both `llm_ask()` and `llm_run()`:

```bash
castor castor:llm:configure
castor castor:llm:configure --global
```

The value can also be passed as an argument, and removed with the `--reset`
option:

```bash
castor castor:llm:configure codex
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

The `llm_ask()` and `llm_run()` functions throw a `Castor\Exception\LlmException`
when the CLI cannot be chosen, because the run is not interactive for instance,
when you refuse to send the prompt, when the configuration of OpenCode cannot be
merged, or when the CLI is not installed, fails, is stopped by a signal or
exceeds the timeout of the context. `llm_run()` also throws when the chosen CLI
cannot modify the project. The message of the exception ends with the error
output of the CLI, to help you fix its setup, or to know what it did before
being stopped.

An empty answer of `llm_run()` is not an error, since the changes matter more
than the last words of the model, but Castor logs a warning: the CLI may have
stopped before doing anything. With the `-vvv` option, Castor also logs the
error output of the CLI, where Codex and OpenCode report what they did.
