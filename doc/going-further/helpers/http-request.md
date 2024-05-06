# HTTP requests

## The `http_request()` function

The `http_request()` function allows to make HTTP(S) requests easily. It
performs HTTP(S) request and returns an instance of
`Symfony\Contracts\HttpClient\ResponseInterface`:

```php
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\http_request;

#[AsTask()]
function foo()
{
    io()->writeln(http_request('GET', 'https://example.org')->getContent());
}
```

## The `http_download()` function

The `http_download()` function simplifies the process of downloading files
through HTTP(S) protocol. It writes the response content directly to a specified
file path.

The `stream` parameter controls whether the download is chunked (`true`, default
value), which is useful for large files as it uses less memory, or in one go
(`false`).

```php
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\http_download;

#[AsTask()]
function foo()
{
    http_download('https://example.org/file', '/path/to/your/local/file', stream: true);
    io()->writeln('Download completed!');
}
```

When running Castor in verbose mode, `http_download()` outputs useful logs,
including a progress indicator to track the download status.

```
18:55:09 INFO      [castor] Filename determined for http download ["filename" => "100MB-speedtest","url" => "http://eu-central-1.linodeobjects.com/speedtest/100MB-speedtest"]
18:55:11 INFO      [castor] Download progress: 29.72 MB/100.00 MB (29.72%) at 18.40 MB/s, ETA: 3s ["url" => "http://eu-central-1.linodeobjects.com/speedtest/100MB-speedtest"]
18:55:13 INFO      [castor] Download progress: 74.94 MB/100.00 MB (74.94%) at 20.73 MB/s, ETA: 1s ["url" => "http://eu-central-1.linodeobjects.com/speedtest/100MB-speedtest"]
18:55:14 INFO      [castor] Download progress: 100.00 MB/100.00 MB (100.00%) at 20.69 MB/s, ETA: 0s ["url" => "http://eu-central-1.linodeobjects.com/speedtest/100MB-speedtest"]
18:55:14 INFO      [castor] Download finished ["url" => "http://eu-central-1.linodeobjects.com/speedtest/100MB-speedtest","filePath" => "/www/castor/100MB-speedtest","size" => "100.00 MB"]
```

## The `http_client()` function

If you need to have a full control on the HTTP(S) client, you can access the
`HttpClientInterface` directly with the `http_client()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\http_client;

#[AsTask()]
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

You can check the [Symfony
documentation](https://symfony.com/doc/current/http_client.html) for more
information about this component and how to use it.
