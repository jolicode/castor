# Waiting for things

`wait_for` and `wait_for_*` functions are designed to help developers handle
various case where waiting is necessary (e.g. waiting for a condition to be
satisfied, for a port to be accessible, etc.).

## Common parameters

The following parameters are common to most of the functions:

- `$timeout` (int): Timeout duration in seconds. Default is 10 seconds.
- `$quiet` (bool): Whether to suppress output. Default is false.
- `$intervalMs` (int): Interval between checks in milliseconds. Default is 100.
- `$message` (?string): Custom message to display before waiting. Default is null.

## How to handle when the condition is met or when timeout is reached

The `wait_for` and `wait_for_*` functions throw a `WaitForTimeoutException`
exception when the timeout is reached. You can catch this exception and handle
it accordingly.

Example:

```php
try {
    wait_for(...); // wait_for_port, wait_for_url, wait_for_http_status, etc.
} catch (WaitForTimeoutException $e) {
    // Handle timeout
}
```

## Usage

### The `wait_for()` function

The `wait_for()` method is a general-purpose waiting function. It takes a
callback function as its first parameter, representing the condition to be met.
The function will repeatedly call this callback until the condition is met or
the specified timeout is reached.

```php
wait_for(
    callback: function () {
        // Your custom condition/callback logic here
        return true; // Change this based on your condition
    },
    timeout: 10,
    quiet: false,
    intervalMs: 100,
    message: 'Waiting for something to happen...',
);
```

> [!NOTE]
> you can also return null if you want to abort the waiting process. The
> function will throw an exception if the callback returns null.

### The `wait_for_port()` function

The `wait_for_port()` method waits for a network port to be accessible. It checks
if a connection can be established to the specified port on a given host within
the specified timeout. The method allows customization by providing options such
as the host.

Example:

```php
wait_for_port(
    port: 8080,
    host: '127.0.0.1',
    timeout: 15,
    quiet: false,
    intervalMs: 500,
    message: 'Waiting for port localhost:8080 to be accessible...',
);
```

### The `wait_for_url()` function

The `wait_for_url()` method waits for a URL to be accessible. It attempts to
open a connection to the specified URL within the specified timeout.

```php
wait_for_url(
    url: 'https://example.com',
    timeout: 10,
    quiet: false,
    intervalMs: 200,
    message: 'Waiting for https://example.com to be accessible...',
);
```

### The `wait_for_http_response()` function

The `wait_for_http_response()` function waits for a specified URL to return a
response assessed using a user-defined `$responseChecker` callback function.
It allows for a detailed validation of the response content.

Example validating the status code and the response content:

```php
wait_for_http_response(
    url: 'https://example.com',
    responseChecker: function (ResponseInterface $response) {
        return $response->getStatusCode() !== 200
        && u($response->getContent())->containsAny(['Example Domain']);
    },
    timeout: 2,
);
```

### The `wait_for_http_status()` function

The `wait_for_http_status()` function is a specialized version of 
`wait_for_http_response()`, specifically designed to monitor a URL until it 
returns a desired HTTP status code.

Example:

```php
wait_for_http_status(
    url: 'https://example.com/api',
    status: 200,
    timeout: 10,
    quiet: false,
    intervalMs: 300,
    message: 'Waiting for https://example.com/api to return HTTP 200',
);
```

### The `wait_for_docker_container()` function

The `wait_for_docker_container()` function waits for a Docker container to be
ready. It checks if the container is running and if the specified port is
accessible within the specified timeout.
It can also wait for a specific check to be successful, by providing a
`$check` callback function.


Example:

```php
wait_for_docker_container(
    container: 'mysql-container',
    containerChecker: function ($containerId) {
        return run("docker exec $containerId mysql -uroot -proot -e 'SELECT 1'", context: context()->withAllowFailure()))->isSuccessful();
    },
    portsToCheck: [3306]
    timeout: 30,
    quiet: false,
    intervalMs: 100,
    message: 'Waiting for my-container to be ready...',
);
```
