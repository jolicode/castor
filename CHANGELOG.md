# CHANGELOG

## Not released yet

### Fixes

* Fix completion when update is available

## 0.17.1 (2024-05-31)

### Fixes

* Fix update instruction

## 0.17.0 (2024-05-27)

### Features

* Add `Castor\Event\AfterBootEvent` to perform action when the application is ready
* Add `Symfony\Component\Finder\SplFileInfo` to the stubs

### Fixes

* Map console verbosity level to default context, even when no context is defined

## 0.16.0 (2024-05-22)

### Features

* Totally rework the import feature. Castor use special `castor.composer.json`
  file to handle imports. This fixes many bugs and issues with the previous
* SSH
    * Marked SSH features as stable
    * Add `?callable $callback = null` param to `ssh_*` functions to manipulate
    output
    * Add support for SSH connection without specifying a user
* Add `context()` function in expression language to enable a task
* Add `notificationTitle` property to `Context` to set the application name for
  notifications title
* Add `http_download()` function to simplify the process of downloading files

### Minor

* Better handling of notification errors and exceptions
* Better log output in debug mode (`-vvv`)
* Changed the behavior of `notify` parameter in `Context` to be a nullable boolean.
  - `null` is now the default value (only user notifications are displayed).
  - `true` to enable notifications globally (user and Castor generated notifications)
  - `false` to disable them globally
* `.castor.stub.php` is now generated in same location where `castor.php` is located

### Deprecations

* Deprecate `Castor\GlobalHelper` class. There are no replacements. Use raw
  functions instead
* Deprecate `AfterApplicationInitializationEvent` event. Use
  `FunctionsResolvedEvent` instead
* Deprecate `request()` in favor of `http_request()` for consistency with newly
  introduced `http_*` function

### Fixes

* Fix root location when repacking application

## 0.15.0 (2024-04-03)

### Features

* Add support for importing remote functions and tasks
* Add a bash installer to ease installation
* Distribute static binaries `castor.darwin-arm64` automatically with the
  release
* Add support for running Castor on Linux arm64 and distribute the binary
  `castor.linux-arm64.phar` automatically with the release
* Add an option `ignoreValidationErrors` on `AsTask` attribute to ignore
  parameters & options validation errors
* Add support for dynamic autocomplete task arguments/options
* Add support for merging an application `box.json` config file used by
  `castor:repack` command
* Find root directory by looking for a `.castor/castor.php` file
* Allow stub file to be in `.castor/.castor.stub.php`

### Fixes

* Fix issue with PTY on windows, it's now always disabled
* Fix issue when finding root dir on windows
* Fix issue on SymfonyTask creation

### Deprecations

* Deprecate loading all PHP files from `[ROOT_DIR]/castor`
* Deprecate `Context::withPath()` in favor of `Context::withWorkingDirectory()`
* Deprecate `path` argument in `capture()`, `exit_code()`, `run()`, `with()` in
  favor of `workingDirectory`
* Deprecate `Castor\TaskDescriptorCollection` in favor of
  `Castor\Descriptor\TaskDescriptorCollection`
* Deprecate `Castor\HasherHelper` in favor of `Castor\Castor\HasherHelper`
* Deprecate `Castor\PathHelper` in favor of `Castor\Castor\PathHelper`

## 0.14.0 (2024-03-08)

* Add a `yaml_dump()` function to dump any PHP value to a YAML string
* Add a `yaml_parse()` function to parse a YAML string to a PHP value
* Remove the default timeout of 60 seconds from the Context
* Add a `recursive` parameter to the `withData()` method of `Context` to allow
  recursive merging for nested arrays
* Add an `open()` function to open a file or URL in the default application
* Add `bool` return type to `fingerprint()` function to indicate if the callable
  was run

## 0.13.1 (2024-02-27)

* Fix instruction for downloading new castor version as a phar

## 0.13.0 (2024-02-23)

* Add a `compile` command that puts together a customizable PHP binary with a
  repacked castor app into a static binary
* Distribute static binaries `castor.linux.amd64` and `castor.darwin.amd64`
  automatically with the release
* Compile watcher and phar for arm64 on macOS, and distribute them with the
  release
* Add `ProcessStartEvent` and `ProcessTerminateEvent` events
* Allow to listen to the symfony console events
* Set the process title according to the current application name and task name
* Deprecates `add_context()` function, use  `AsContextGenerator` attribute
  instead
* Allow to get null instead of throwing an exception when calling `task(true)`
  without a current task
* Ignore some low level env vars in runnable command showed in logs
* Fix section output to work on Windows

## 0.12.1 (2024-02-06)

* Fix issue with symfony console (color doesn't work in tmux or screen)

## 0.12.0 (2024-02-06)

* Add a `debug` command
* Add `guard_min_version()` function to ensure a minimum version of Castor is used
* Add `wait_for_http_response()` function for a more generic response check
* Add `wait_for_docker_container()` function to wait for a docker container to be ready
* Add `AsSymfonyTask` attribute to map Symfony Command
* Add `Context->name` property (automatically set by the application)
* Add an error handler, and wire the logger to it so display deprecation notices
* Edited the duration of update check from `60 days` to `24 hours`
* Revise the usage of the terms `command` and `task` for consistency through code and docs.
* [BC Break] Remove `callable $responseChecker` parameter from `wait_for_http_status()`
* [BC Break] The event `AfterApplicationInitializationEvent` second arguments is now a
  `TaskDescriptorCollection`, and the event is emitted after the context configuration

## 0.11.1 (2024-01-11)

* Fix issue when using `ContextRegistry::getCurrentContext()` without setting first a context
* Calling `ContextRegistry::getCurrentContext()` without `setCurrentContext()`
  is deprecated. Pass a `$context` instead to the function, or set a current
  context before.

## 0.11.0 (2024-01-11)

* Add `AsListener` attribute to register an event listener
* Add `wait_for()`, `wait_for_port()`, `wait_for_url()`, `wait_for_http_status()` functions
* Allow to override `AsTask` and `AsContext` attributes
* Add `force` argument to `fingerprint()` method to force run the callable, even if fingerprint is same
* Fix directory for fingerprinted test
* [BC Break] Remove almost all setters in the GlobalHelper class
* Refactor the documentation

## 0.10.0 (2023-11-14)

* Add `ssh_upload()` and `ssh_download()` functions to upload/download files via SSH
* Rename `ssh()` to `ssh_run()`
* Allow to set default context with an env variable

## 0.9.1 (2023-10-09)

* Fix castor application version
* Fix typo in `run()` error message

## 0.9.0 (2023-10-09)

* Add `fingerprint()` function to condition code execution based on some hash changes
* Better handle default Symfony commands when no castor file exists yet
* Add `-c` option to `castor` command to specify a context

## 0.8.0 (2023-08-16)

* Add `request()` and `http_client()` functions to make HTTP requests
* Add support for disabling task dynamically
* Add a `with` function to run logic with a specific context or parameters
  without passing them to each `run` or other functions
* Allow to get a context by its name using `$fooContext = context('foo')`
* Experimental display with sections, allow better output when using parallel
  function, enable it by by using `CASTOR_USE_SECTION=true castor [task]`
* Display more information when running a process
* Rename `get_exit_code()` to `exit_code()`
* Rename `get_context()` to `context()`
* Rename `get_input()` to `input()`
* Rename `get_output()` to `output()`
* Rename `get_application()` to `app()`
* Rename `get_command()` to `task()`
* Fix parallel when one of the callback fails, wait for the others to finish to
  throw exception

## 0.7.1 (2023-07-11)

* Fix the `castor --version` command when there is no `.castor.php` file

## 0.7.0 (2023-07-11)

* Add support for re-packing a castor application into a new phar file
* Fix the update command message to follow redirects with curl

## 0.6.0 (2023-06-30)

* Add support for registering `Context` programmatically
* Add `load_dot_env()` function for loading the context's environment from a dotenv file
* Add support for multiple paths in `watch()` function
* Add `get_exit_code()` function to get a process exit code, even if it failed

## 0.5.2 (2023-06-24)

* Add documentation about installation in a Github Action
* Add more classes in stubs

## 0.5.1 (2023-06-22)

* Fix curl download in installation instructions
* Fix code on initial castor.php creation
* Do not remove annotation from phar

## 0.5.0 (2023-06-16)

* Add support for signals handling
* Add a way to type Context::$data

## 0.4.1 (2023-06-13)

* Allow to use the cache in the context creator
* Add `onFailure` argument to the `capture()` function
* Add `ExecutableFinder` in stubs

## 0.4.0 (2023-06-12)

* [BC Break] replace specials helpers arguments by dedicated functions
* Add `capture()` function to easily run a process and returns the output
* Add `cache()` and `get_cache()` function to easily cache something
* Add `ssh()` function to run commands on remote server via SSH
* Display warning and update instructions when a new version is available
* Better error reporting when a call to `run()` fails or when `import()` is not possible
* Fix stubs generation

## 0.3.0 (2023-06-07)

* Enhance the documentation
* Enhance the first run experience

## 0.2.0 (2023-06-02)

* Add a way to get the `Command` instance in a task
* Add support for better handling of option without value
* Fix the stubs generation when castor is installed via composer
* Fix the initial `castor.php` file generated for new projects
* Fix `watch()` function

## 0.1.0 (2023-05-21)

* Initial release
