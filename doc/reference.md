# Castor reference

Here is a reference of all the functions and attributes provided by Castor.

## Functions

Castor provides the following built-in functions:

- [`app`](going-further/helpers/console-and-io.md#the-app-function)
- [`cache`](going-further/helpers/cache.md#the-cache-function)
- [`capture`](getting-started/run.md#the-capture-function)
- [`context`](getting-started/context.md#the-context-function)
- [`exit_code`](getting-started/run.md#the-exit_code-function)
- [`finder`](going-further/helpers/filesystem.md#the-finder-function)
- [`fingerprint`](going-further/helpers/fingerprint.md#the-fingerprint-function)
- [`fingerprint_exists`](going-further/helpers/fingerprint.md#the-fingerprint_exists-and-fingerprint_save-functions)
- [`fingerprint_save`](going-further/helpers/fingerprint.md#the-fingerprint_exists-and-fingerprint_save-functions)
- [`fs`](going-further/helpers/filesystem.md#the-fs-function)
- [`get_cache`](going-further/helpers/cache.md#the-get_cache-function)
- [`guard_min_version`](going-further/helpers/version-check.md#the-guard_min_version-function)
- [`hasher`](going-further/helpers/fingerprint.md#the-hasher-function)
- [`http_client`](going-further/helpers/http-request.md#the-http_client-function)
- [`import`](getting-started/basic-usage.md#the-import-function)
- [`input`](going-further/helpers/console-and-io.md#the-input-function)
- [`io`](going-further/helpers/console-and-io.md#the-io-function)
- [`load_dot_env`](going-further/interacting-with-castor/dot-env.md#the-load_dot_env-function)
- [`log`](going-further/interacting-with-castor/log.md#the-log-function)
- [`logger`](going-further/interacting-with-castor/log.md#the-logger-function)
- [`notify`](going-further/helpers/notify.md#the-notify-function)
- [`output`](going-further/helpers/console-and-io.md#the-output-function)
- [`parallel`](going-further/helpers/parallel.md#the-parallel-function)
- [`request`](going-further/helpers/http-request.md#the-request-function)
- [`run`](getting-started/run.md#the-run-function)
- [`ssh_download`](going-further/helpers/ssh.md#the-ssh_download-function)
- [`ssh_run`](going-further/helpers/ssh.md#the-ssh_run-function)
- [`ssh_upload`](going-further/helpers/ssh.md#the-ssh_upload-function)
- [`task`](going-further/helpers/console-and-io.md#the-task-function)
- [`variable`](getting-started/context.md#the-variable-function)
- [`wait_for`](going-further/helpers/wait-for.md#the-wait_for-function)
- [`wait_for_docker_container`](going-further/helpers/wait-for.md#the-wait_for_docker_container-function)
- [`wait_for_http_response`](going-further/helpers/wait-for.md#the-wait_for_http_response-function)
- [`wait_for_http_status`](going-further/helpers/wait-for.md#the-wait_for_http_status-function)
- [`wait_for_port`](going-further/helpers/wait-for.md#the-wait_for_port-function)
- [`wait_for_url`](going-further/helpers/wait-for.md#the-wait_for_url-function)
- [`watch`](going-further/helpers/watch.md)
- [`with`](going-further/interacting-with-castor/advanced-context.md#the-with-function)
- [`yaml_dump`](going-further/helpers/yaml.md)
- [`yaml_parse`](going-further/helpers/yaml.md)

## Attributes

Castor provides the following attributes to register tasks, listener, etc:

- [`AsArgument`](getting-started/arguments.md#overriding-the-argument-name-and-description)
- [`AsContext`](getting-started/context.md#creating-a-new-context)
- [`AsContextGenerator`](going-further/interacting-with-castor/advanced-context.md#the-ascontextgenerator-attribute)
- [`AsListener`](going-further/extending-castor/events.md#registering-a-listener)
- [`AsOption`](getting-started/arguments.md#overriding-the-option-name-and-description)
- [`AsSymfonyTask`](going-further/interacting-with-castor/symfony-task.md)
- [`AsTask`](getting-started/basic-usage.md)
