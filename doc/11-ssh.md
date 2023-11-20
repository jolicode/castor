# SSH and remote servers

## Run SSH commands

Castor supports running commands on remote servers through SSH with the `ssh_run()`
function:

```php
use Castor\Attribute\AsTask;

use function Castor\ssh_run;

#[AsTask]
function ls(): void
{
    // List content of /var/www directory on the remote server
    ssh_run('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}
```

> **Note**
> This feature is marked as experimental and may change in the future.

## Upload and download files

Castor provides 2 functions `ssh_upload()` and `ssh_download()` to exchange files
between localhost and a remote server:

```php
use function Castor\ssh_download;
use function Castor\ssh_upload;

#[AsTask]
function upload_file(): void
{
    ssh_upload('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}

#[AsTask]
function download_file(): void
{
    ssh_download('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
```

> **Note**
> These functions are marked as experimental and may change in the future.

## Available SSH options

All `ssh_xxx()` functions offer an additional `ssh_options` argument to configure
the SSH connection. The following options are currently available:

- `port`: port to use to connect to the remote server (default: 22)
- `path_private_key`: path to the private key to use to connect to the remote
server
- `jump_host`: host to use as a jump host
- `multiplexing_control_path`: path to the control socket for multiplexing 
connections
- `multiplexing_control_persist`: whether to persist the control socket for 
multiplexing connections or idle time after which the backgrounded master
connection will automatically terminate (default: no)
- `enable_strict_check`: whether to enable strict host key checking
(default: true)
- `password_authentication`: whether to use password authentication
(default: false)
