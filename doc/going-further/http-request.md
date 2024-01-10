# HTTP requests

## The `request()` function

The `request()` function allows to make HTTP requests easily. It performs HTTP
request and returns an instance of
`Symfony\Contracts\HttpClient\ResponseInterface`:

```php
use Castor\Attribute\AsTask;

use function Castor\request;

#[AsTask]
function foo()
{
    echo request('GET', 'https://example.org')->getContent(), \PHP_EOL;
}
```

## The `http_client()` function

If you need to have a full control on the HTTP client, you can access the
`HttpClientInterface` directly with the `http_client()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\http_client;

#[AsTask]
function foo()
{
    $client = http_client()
        ->withOptions([
            'verify_peer' => false,
            'timeout' => 10,
        ])
    ;
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/http_client.html)
for more information about this component and how to use it.
