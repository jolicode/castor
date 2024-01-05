# Wait For Utilities (Network util to wait for port, http code, or anything with callback)

`wait_for` and `wait_for_*` is designed to help developers handle various network-related scenarios by providing functions to wait for specific conditions, such as port accessibility, URL availability, and HTTP status codes. It offers flexibility and ease of use in scenarios where waiting for network-related events is necessary.

## Before you begin

### Common parameters

The following parameters are common to most of the functions:

`$timeout` (int): Timeout duration in seconds. Default is 10 seconds.

`$name` (string|null): A custom name for the condition (optional).

`$throw` (bool): Whether to throw an exception on timeout. Default is false and will return false on timeout or true on success.

`$quiet` (bool): Whether to suppress output. Default is false.

`$intervalMs` (int): Interval between checks in milliseconds. Default is 100.

## How to handle when the condition is met or when timeout is reached

The `wait_for` and `wait_for_*` functions return a boolean value indicating whether the condition was met or not. If the condition was met, the function will return true. If the condition was not met within the specified timeout, the function will return false. If the `$throw` parameter is set to true, the function will throw a `WaitForTimeoutException` exception on timeout.

### Examples:

#### How to handle return values

```php
$result = wait_for(...); // wait_for_port, wait_for_url, wait_for_http_status, etc.

if ($result) {
    // Condition was met
} else {
    // Condition was not met
}
```

#### How to handle exceptions

```php
try {
    $result = wait_for(...); // wait_for_port, wait_for_url, wait_for_http_status, etc.
} catch (WaitForTimeoutException $e) {
    // Handle timeout
}
```

## Usage

### `wait_for`

#### Explanation:

The `wait_for` method is a general-purpose waiting function. It takes a callback function as its first parameter, representing the condition to be met. The function will repeatedly call this callback until the condition is met or the specified timeout is reached.

#### Examples:

1. **Waiting for a custom condition to be met:**
   ```php
   $result = wait_for(
       function () {
           // Your custom condition/callback logic here
           return true; // Change this based on your condition
       },
       $timeout = 10,
       $name = 'My Custom Condition',
       $throw = false,
       $quiet = false,
       $intervalMs = 100,
       $message = 'Waiting for %s to be available...', // %s will be replaced with the $name parameter
       $successMessage = ' <fg=green> OK %s is available !</>' // %s will be replaced with the $name parameter
   );
   ```

2. **Waiting for a simple condition using a closure:**
   ```php
   $result = wait_for(
       fn () => file_exists('/path/to/file.txt'),
       $timeout = 5,
       $name = 'file',
       $throw = true,
       $quiet = false,
       $intervalMs = 200,
       $message = 'Waiting for %s to be available...',
       $successMessage = ' <fg=green> OK %s is available !</>'
   );
   ```

### `wait_for_port`

#### Explanation:

The `wait_for_port` method waits for a network port to be accessible. It checks if a connection can be established to the specified port on a given host within the specified timeout. The method allows customization by providing options such as the host.

#### Example:

```php
$result = wait_for_port(
    $port = 8080,
    $host = '127.0.0.1',
    $timeout = 15,
    $name = 'My Local Server',
    $throw = false,
    $quiet = false,
    $intervalMs = 500
);
```

### `wait_for_url`

#### Explanation:

The `wait_for_url` method waits for a URL to be accessible. It attempts to open a connection to the specified URL within the specified timeout.

#### Example:

```php
$result = wait_for_url(
    $url = 'http://example.com',
    $timeout = 10,
    $name = 'Example Website',
    $throw = true,
    $quiet = false,
    $intervalMs = 200
);
```

### `wait_for_http_status`

#### Explanation:

The `wait_for_http_status` method waits for a URL to return a specific HTTP status code. It checks if the URL returns the expected status code within the specified timeout. Additionally, it allows a custom content checker callback to further validate the response content.
The method provide `$contentCheckerCallback` parameter to check the response content and return true if the content is valid, or false if the content is invalid

#### Example:

```php
$result = wait_for_http_status(
    $url = 'https://example.com/api',
    $status = 200,
    $contentCheckerCallback = fn (array|string $content) => isset($content['result']), // Type depends on the response content type (array for JSON application/json, string for text/plain, etc.)
    $timeout = 10,
    $name = 'My Beautiful API',
    $throw = false,
    $quiet = false,
    $intervalMs = 300
);
```
