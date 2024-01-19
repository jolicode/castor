# Castor reference

Here is a reference of all the functions and attributes provided by Castor.

## Functions

Castor provides the following built-in functions:

- [`add_context`](going-further/advanced-context.md#the-add_context-function)
- [`app`](going-further/console-and-io.md#the-app-function)
- [`cache`](going-further/cache.md#the-cache-function)
- [`capture`](03-run.md#the-capture-function)
- [`context`](05-context.md#the-context-function)
- [`exit_code`](03-run.md#the-exit_code-function)
- [`finder`](going-further/filesystem.md#the-finder-function)
- [`fingerprint`](going-further/fingerprint.md#the-fingerprint-function)
- [`fingerprint_exists`](going-further/fingerprint.md#the-fingerprint_exists-and-fingerprint_save-functions)
- [`fingerprint_save`](going-further/fingerprint.md#the-fingerprint_exists-and-fingerprint_save-functions)
- [`fs`](going-further/filesystem.md#the-fs-function)
- [`get_cache`](going-further/cache.md#the-get_cache-function)
- [`guard_min_version`](going-further/version-check.md#the-guard_min_version-function)
- [`hasher`](going-further/fingerprint.md#the-hasher-function)
- [`http_client`](going-further/http-request.md#the-http_client-function)
- [`import`](02-basic-usage.md#the-import-function)
- [`input`](going-further/console-and-io.md#the-input-function)
- [`io`](going-further/console-and-io.md#the-io-function)
- [`load_dot_env`](going-further/dot-env.md#the-load_dot_env-function)
- [`log`](going-further/log.md#the-log-function)
- [`logger`](going-further/log.md#the-logger-function)
- [`notify`](going-further/notify.md#the-notify-function)
- [`output`](going-further/console-and-io.md#the-output-function)
- [`parallel`](going-further/parallel.md#the-parallel-function)
- [`request`](going-further/http-request.md#the-request-function)
- [`task`](going-further/console-and-io.md#the-task-function)
- [`run`](03-run.md#the-run-function)
- [`ssh_download`](going-further/ssh.md#the-ssh_download-function)
- [`ssh_run`](going-further/ssh.md#the-ssh_run-function)
- [`ssh_upload`](going-further/ssh.md#the-ssh_upload-function)
- [`variable`](05-context.md#the-variable-function)
- [`wait_for`](going-further/wait-for.md#the-wait_for-function)
- [`wait_for_url`](going-further/wait-for.md#the-wait_for_url-function)
- [`wait_for_http_response`](going-further/wait-for.md#the-wait_for_http_response-function)
- [`wait_for_http_status`](going-further/wait-for.md#the-wait_for_http_status-function)
- [`wait_for_port`](going-further/wait-for.md#the-wait_for_port-function)
- [`wait_for_docker_container`](going-further/wait-for.md#the-wait_for_docker_container-function)
- [`watch`](going-further/watch.md)
- [`with`](going-further/advanced-context.md#the-with-function)

## Attributes

Castor provides the following attributes to register tasks, listener, etc:

- [`AsArgument`](04-arguments.md#overriding-the-argument-name-and-description)
- [`AsContext`](05-context.md#creating-a-new-context)
- [`AsListener`](going-further/events.md#registering-a-listener)
- [`AsOption`](04-arguments.md#overriding-the-option-name-and-description)
- [`AsSymfonyTask`](going-further/symfony-task.md)
- [`AsTask`](02-basic-usage.md)
