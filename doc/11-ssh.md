# SSH and remote servers

Castor support running commands on remote servers through SSH. You can configure
the host on which you want to run commands via the `Context` object:

```php
use Castor\Context;

#[AsTask]
function ssh(Context $context): void
{
    $context = $context->withSsh('server-1.example.com', 'debian', [
        'port' => 2222,
    ]);
    
    run('ls -alh', context: $context); // will list content of the home directory on the remote server
}
```

> **Note**
> Only the `run()` function is able to interact with remote servers.

## Available options

You can pass additional options to the `withSsh` method.
The following options are currently available:

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
