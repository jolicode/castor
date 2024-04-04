# SSH and remote servers

Castor provide several `ssh_*` functions to run SSH commands on remote servers
or upload/download files through SCP.

## Common parameters

The following parameters are common to all of the `ssh_*()` functions:

- `$host` (string): Host to connect to.
- `$user` (string): Optional user to connect with.
- `$ssh_options` (array): Optional configuration of the connexion.
 
The `ssh_options` argument supports the following options:

- `port` (int): port to use to connect to the remote server (default: 22)
- `path_private_key` (string): path to the private key to use to connect to the
remote server
- `jump_host` (string): host to use as a jump host
- `multiplexing_control_path` (string): path to the control socket for
multiplexing connections
- `multiplexing_control_persist` (string): whether to persist the control socket
for multiplexing connections or idle time after which the backgrounded master
connection will automatically terminate (default: no)
- `enable_strict_check` (bool): whether to enable strict host key checking
  (default: true)
- `password_authentication` (bool): whether to use password authentication
  (default: false)

## The `ssh_run()` function

Castor supports running commands on remote servers through SSH with the
`ssh_run()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\ssh_run;

#[AsTask()]
function ls(): void
{
    // List content of /var/www directory on the remote server
    ssh_run('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}
```

## Upload and download files

Castor provides 2 functions `ssh_upload()` and `ssh_download()` to exchange files
between localhost and a remote server:

### The `ssh_upload()` function

```php
use function Castor\ssh_upload;

#[AsTask()]
function upload_file(): void
{
    ssh_upload('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
```

### The `ssh_download()` function

```php
use function Castor\ssh_download;

#[AsTask()]
function download_file(): void
{
    ssh_download('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
```
