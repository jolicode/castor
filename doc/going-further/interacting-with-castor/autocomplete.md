# Autocomplete

## Installation

If you use bash, you can enable autocomplete for castor by running the
following task:

```
castor completion | sudo tee /etc/bash_completion.d/castor
```

Then reload your shell.

Others shells are also supported (zsh, fish, etc). To get the list of supported
shells and their dedicated instructions, run:

```
castor completion --help
```

## Autocomplete arguments

You have two options to make your arguments autocompleted.

### Static suggestions

In case your suggestions are fixed, you can pass them as an array in the
`autocomplete` property of the `AsArgument` and `AsOption` attributes:

```php
#[AsTask()]
function my_task(
    #[AsArgument(name: 'argument', autocomplete: ['foo', 'bar', 'baz'])]
    string $argument,
): void {
}
```

When trying to autocomplete the arguments, your shell will now suggest these
values:

```bash
$ castor my-task [TAB]
bar  baz  foo
```

### Dynamic suggestions

In case you need some logic to list the suggestions (like suggesting paths or
docker services, making a database query or HTTP request to determine some
values, etc.), you can use the same `autocomplete` property of the `AsArgument`
and `AsOption` attributes to provide the function that will return the
suggestions:

```php
namespace example;

use Symfony\Component\Console\Completion\CompletionInput;

#[AsTask()]
function autocomplete_argument(
    #[AsArgument(name: 'argument', autocomplete: 'example\get_argument_autocompletion')]
    string $argument,
): void {
}

function get_argument_autocompletion(CompletionInput $input): array
{
    // You can search for a file on the filesystem, make a network call, etc.

    return [
        'foo',
        'bar',
        'baz',
    ];
}
```

>[!NOTE]
> Because the syntax `my_callback(...)` is not allowed on attribute, you need to
> specify the `autocomplete` callback with either:
>
> - the string syntax (`my_namespace\my_function` or `'MyNamespace\MyClass::myFunction'`)
> - the array syntax (`['MyNamespace\MyClass', 'myFunction']`).

This function receives an optional `Symfony\Component\Console\Completion\CompletionInput`
argument to allow you to pre-filter the suggestions returned to the shell.

>[!TIP]
> The shell script is able to handle huge amounts of suggestions and will
> automatically filter the suggested values based on the existing input from the
> user. You do not have to implement any filter logic in the function.
> 
> You may use CompletionInput::getCompletionValue() to get the current input if
> that helps to improve performance (e.g. by reducing the number of rows fetched
> from the database).
