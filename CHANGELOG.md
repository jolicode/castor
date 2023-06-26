# CHANGELOG

## Unreleased

* Add support for registering `Context` programmatically
* Add `load_dot_env()` function for loading the context's environment from a dotenv file

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

* Add support for re-packing a castor application into a new phar file

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
