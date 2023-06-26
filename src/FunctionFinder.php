<?php

namespace Castor;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Symfony\Component\Finder\Finder;

/** @internal */
class FunctionFinder
{
    /** @var array<string> */
    public static array $files = [];

    /** @return iterable<TaskDescriptor|ContextDescriptor> */
    public function findFunctions(string $path): iterable
    {
        yield from self::doFindFunctions([new \SplFileInfo($path . '/castor.php')]);

        $castorDirectory = $path . '/castor';

        if (is_dir($castorDirectory)) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->in($castorDirectory)
            ;

            yield from self::doFindFunctions($files);
        }
    }

    /**
     * @param iterable<\SplFileInfo> $files
     *
     * @return iterable<TaskDescriptor|ContextDescriptor>
     *
     * @throws \ReflectionException
     */
    public function doFindFunctions(iterable $files): iterable
    {
        $existingFunctions = get_defined_functions()['user'];

        foreach ($files as $file) {
            castor_require($file->getRealPath());

            $newExistingFunctions = get_defined_functions()['user'];

            $newFunctions = array_diff($newExistingFunctions, $existingFunctions);
            $existingFunctions = $newExistingFunctions;

            foreach ($newFunctions as $functionName) {
                $reflectionFunction = new \ReflectionFunction($functionName);

                $attributes = $reflectionFunction->getAttributes(AsTask::class);
                if (\count($attributes) > 0) {
                    $taskAttribute = $attributes[0]->newInstance();

                    if ('' === $taskAttribute->name) {
                        $taskAttribute->name = SluggerHelper::slug($reflectionFunction->getShortName());
                    }

                    if (null === $taskAttribute->namespace) {
                        $ns = str_replace('/', ':', \dirname(str_replace('\\', '/', $reflectionFunction->getName())));
                        $ns = implode(':', array_map(SluggerHelper::slug(...), explode(':', $ns)));
                        $taskAttribute->namespace = $ns;
                    }

                    foreach ($taskAttribute->onSignals as $signal => $callable) {
                        if (!\is_callable($callable)) {
                            throw new \LogicException(sprintf('The callable for signal "%s" is not callable.', $signal));
                        }
                    }

                    yield new TaskDescriptor($taskAttribute, $reflectionFunction);

                    continue;
                }

                $attributes = $reflectionFunction->getAttributes(AsContext::class);
                if (\count($attributes) > 0) {
                    $contextAttribute = $attributes[0]->newInstance();

                    if ('' === $contextAttribute->name) {
                        if ($contextAttribute->default) {
                            $contextAttribute->name = 'default';
                        } else {
                            $contextAttribute->name = SluggerHelper::slug($reflectionFunction->getShortName());
                        }
                    }

                    yield new ContextDescriptor($contextAttribute, $reflectionFunction);
                }
            }
        }
    }
}

// Don't leak internal variables
/** @internal */
function castor_require(string $file): void
{
    FunctionFinder::$files[] = $file;

    require_once $file;
}
