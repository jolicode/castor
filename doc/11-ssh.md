# SSH and remote servers

Castor supports running commands on remote servers through SSH with the `ssh()`
function:

```php
use Castor\Attribute\AsTask;

use function Castor\ssh;

#[AsTask]
function ls(): void
{
    // List content of /var/www directory on the remote server
    ssh('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}
```

> **Note**
> This feature is marked as experimental and may change in the future.

## Available options

You can pass additional options in the `ssh_options` argument of the `ssh()`
function. The following options are currently available:

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
