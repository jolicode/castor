# Frequently Asked Questions

## How is **Castor** different from **Robo**?

| Feature                  | **Castor**                                                   | **Robo**                                              |
| ------------------------ |--------------------------------------------------------------|-------------------------------------------------------|
| 💡 Philosophy            | Simple PHP **functions with attributes**                     | **OOP-based** task classes                            |
| ⚙️ Task definition       | Annotated PHP functions (`#[AsTask]`)                        | Methods inside a class extending `Tasks`              |
| 📦 Installation          | Phar, static binary, Composer, Github Action                 | Phar, Composer                                        |
| 🧠 CLI autocompletion    | ✅ Built-in                                                   | ❌ Not by default                                      |
| 🧪 Learning curve        | Very low – just write a function                             | Requires boilerplate and understanding of inheritance |
| 🛠️ Modern PHP           | ✅ Uses modern features (attributes, PHP 8+)                  | ⚠️ More traditional OOP, less "modern PHP"-oriented   |
| 🪄 Symfony Console-based | ✅ Yes (under the hood)                                       | ✅ Yes (used directly)                                 |
| 👥 Community             | Small but active (built by [JoliCode](https://jolicode.com)) | Larger but not very active                            |

**TL;DR**

- Castor is minimal, expressive, and easy to use. You define tasks as plain PHP functions with attributes — that’s it.
- Robo is powerful but more verbose and class-oriented. It might feel too heavy for small or script-like automation needs.

> If you prefer "just PHP" over complex CLI frameworks, you'll love Castor.<br>
> If you're building a full-featured CLI app, Robo might fit — but Castor often gets you there faster.

## How is **Castor** different from **Make**?

| Feature                  | **Castor**                                | **Make**                                                    |
| ------------------------ | ----------------------------------------- |-------------------------------------------------------------|
| 💡 Language              | **PHP** — your own language               | **Makefile syntax** (custom DSL)                            |
| 🧠 Learning curve        | Easy for any PHP dev                      | Steep if you're not familiar with Makefile syntax nor Shell |
| 🧰 Task definition       | PHP functions with attributes             | Rule-based, using targets and dependencies                  |
| 🪄 Dynamic logic         | ✅ Native in PHP (conditions, loops, etc.) | ⚠️ Harder — requires shell scripting or complex rules       |
| 💥 Error handling        | Try/catch, logging, etc. in PHP           | Shell-based error codes and operators (like `&&`)           |
| 🧩 Dependencies          | Composer-managed                          | External shell commands                                     |
| 🌍 Cross-platform        | ✅ Fully portable (runs with PHP)          | ⚠️ Depends on shell tools — may vary on Windows/Linux       |
| 🧪 Designed for PHP devs | ✅ Yes                                     | ❌ Not really                                                |

**TL;DR**

* **Make** is great for compiling C projects in 1995.
* **Castor** is great for automating (PHP or not) projects in 2025.

> Make is powerful, but its syntax is obscure and hard to debug.<br>
> Castor lets you write tasks in PHP — the language you already know.

## How is **Castor** different from raw **Symfony Console** usage?

Castor is a task runner, so it's primary goal is to run simple tasks to simplify
the project development. Usually, it is used to run Docker commands, database
migrations, cache clearing, etc.

Usually, tasks are very small, like 1 or 2 lines of code. So you probably don't
want to waste your project with ops command that are not strictly related to the
business.

## Why "Castor"?

Castor means "beaver" in french. It's an animal building stuff. And this is what
this tool does: it helps you build stuff 😁
