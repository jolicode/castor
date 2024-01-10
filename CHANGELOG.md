# CHANGELOG

## Not released yet

* Add `force` argument to `fingerprint()` method to force run the callable, even if fingerprint is same
* Allow to override `AsTask` and `AsContext` attributes
* Fix directory for fingerprinted test
* Add `wait_for()` function to wait for a condition to be true by using a
  callback
* Add `wait_for_port()` function to wait for a port to be open
* Add `wait_for_url()` function to wait for an URL to be accessible
* Add `wait_for_http_status()` function to wait for an URL to return a specific
  HTTP status code (can check the response content too)

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
