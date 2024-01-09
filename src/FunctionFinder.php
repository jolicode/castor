<?php

namespace Castor;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\String\u;

/** @internal */
class FunctionFinder
{
    /** @var array<string> */
    public static array $files = [];

    /** @return iterable<TaskDescriptor|ContextDescriptor|ListenerDescriptor> */
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
     * @return iterable<TaskDescriptor>
     */
    private function resolveTasks(\ReflectionFunction $reflectionFunction): iterable
    {
        $attributes = $reflectionFunction->getAttributes(AsTask::class, \ReflectionAttribute::IS_INSTANCEOF);
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
        }
    }

    /**
     * @return iterable<ContextDescriptor>
     */
    private function resolveContexts(\ReflectionFunction $reflectionFunction): iterable
    {
        $attributes = $reflectionFunction->getAttributes(AsContext::class, \ReflectionAttribute::IS_INSTANCEOF);
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

        return $attributes;
    }

    /**
     * @param iterable<\SplFileInfo> $files
     *
     * @return iterable<TaskDescriptor|ContextDescriptor|ListenerDescriptor>
     *
     * @throws \ReflectionException
     */
    private function doFindFunctions(iterable $files): iterable
    {
        $existingFunctions = get_defined_functions()['user'];

        foreach ($files as $file) {
            castor_require($file->getPathname());

            $newExistingFunctions = get_defined_functions()['user'];

            $newFunctions = array_diff($newExistingFunctions, $existingFunctions);
            $existingFunctions = $newExistingFunctions;

            foreach ($newFunctions as $functionName) {
                $reflectionFunction = new \ReflectionFunction($functionName);

                yield from $this->resolveTasks($reflectionFunction);
                yield from $this->resolveContexts($reflectionFunction);
                yield from $this->resolveListeners($reflectionFunction);
            }
        }
    }

    /**
     * @return iterable<ListenerDescriptor>
     */
    private function resolveListeners(\ReflectionFunction $reflectionFunction): iterable
    {
        $attributes = $reflectionFunction->getAttributes(AsListener::class);
        if (\count($attributes) > 0) {
            foreach ($attributes as $attribute) {
                /** @var AsListener $listenerAttribute */
                $listenerAttribute = $attribute->newInstance();

                if (u($listenerAttribute->event)->endsWith('::class') && !class_exists($listenerAttribute->event)) {
                    throw new \LogicException(sprintf('The event "%s" does not exist.', $listenerAttribute->event));
                }

                yield new ListenerDescriptor($listenerAttribute, $reflectionFunction);
            }
        }
    }
}

// Don't leak internal variables
/** @internal */
function castor_require(string $file): void
{
    if (!is_file($file)) {
        throw new \RuntimeException(sprintf('Could not find file "%s".', $file));
    }

    FunctionFinder::$files[] = $file;

    require_once $file;
}
