<?php

namespace Castor;

class PathHelper
{
    public static function getRoot() : string
    {
    }
    public static function realpath(string $path) : string
    {
    }
}
namespace Castor;

/**
 * @return array<mixed>
 */
function parallel(callable ...$callbacks) : array
{
}
/**
 * @param string|array<string>                           $command
 * @param (callable(string, string, Process) :void)|null $callback
 * @param array<string, string>|null                     $environment
 */
function exec(string|array $command, array|null $environment = null, string|null $path = null, bool|null $tty = null, bool|null $pty = null, float|null $timeout = null, bool|null $quiet = null, bool|null $allowFailure = null, bool|null $notify = null, callable $callback = null, Context $context = null) : \Symfony\Component\Process\Process
{
}
function notify(string $message) : void
{
}
/** @param (callable(string, string) : (false|null)) $function */
function watch(string $path, callable $function, Context $context = null) : void
{
}
/**
 * @param array<string, mixed> $context
 */
function log(string $message, string $level = 'info', array $context = []) : void
{
}
function import(string $path) : void
{
}
namespace Castor;

class Context implements \ArrayAccess
{
    public readonly string $currentDirectory;
    /**
     * @param array<(int|string), mixed> $data        The input parameter accepts an array or an Object
     * @param array<string, string>      $environment A list of environment variables to add to the command
     */
    public function __construct(public readonly array $data = [], public readonly array $environment = [], string $currentDirectory = null, public readonly bool $tty = false, public readonly bool $pty = true, public readonly float|null $timeout = 60, public readonly bool $quiet = false, public readonly bool $allowFailure = false, public readonly bool $notify = false)
    {
    }
    /** @param array<(int|string), mixed> $data */
    public function withData(array $data, bool $keepExisting = true) : self
    {
    }
    /** @param array<string, string> $environment */
    public function withEnvironment(array $environment, bool $keepExisting = true) : self
    {
    }
    public function withPath(string $path) : self
    {
    }
    public function withTty(bool $tty = true) : self
    {
    }
    public function withPty(bool $pty = true) : self
    {
    }
    public function withTimeout(float|null $timeout) : self
    {
    }
    public function withQuiet(bool $quiet = true) : self
    {
    }
    public function withAllowFailure(bool $allowFailure = true) : self
    {
    }
    public function withNotify(bool $notify = true) : self
    {
    }
    public function offsetExists(mixed $offset) : bool
    {
    }
    public function offsetGet(mixed $offset) : mixed
    {
    }
    public function offsetSet(mixed $offset, mixed $value) : void
    {
    }
    public function offsetUnset(mixed $offset) : void
    {
    }
}
namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsTask
{
    /**
     * @param array<string> $aliases
     */
    public function __construct(public string $name = '', public string|null $namespace = null, public string $description = '', public array $aliases = [])
    {
    }
}
namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsContext
{
    public function __construct(public string $name = '', public bool $default = false)
    {
    }
}
namespace Castor\Attribute;

abstract class AsCommandArgument
{
    public function __construct(public readonly string|null $name = null)
    {
    }
}
namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsArgument extends AsCommandArgument
{
    /**
     * @param array<string> $suggestedValues
     */
    public function __construct(string|null $name = null, public readonly string $description = '', public readonly array $suggestedValues = [])
    {
    }
}
namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsOption extends AsCommandArgument
{
    /**
     * @param string|array<string>|null $shortcut
     * @param array<string>             $suggestedValues
     */
    public function __construct(string|null $name = null, public readonly string|array|null $shortcut = null, public readonly int|null $mode = null, public readonly string $description = '', public readonly array $suggestedValues = [])
    {
    }
}