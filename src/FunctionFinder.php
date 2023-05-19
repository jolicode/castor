<?php

namespace Castor;

use Castor\Attribute\AsContext;
use Castor\Attribute\Task;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FunctionFinder
{
    /** @return iterable<TaskDescriptor|ContextBuilder> */
    public function findFunctions(string $path): iterable
    {
        $finder = Finder::create()
            ->directories()
            ->ignoreDotFiles(false)
            ->name('.castor')
            ->in($path)
        ;

        foreach ($finder as $directory) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->in($directory->getRealPath())
            ;

            yield from $this->doFindFunctions($files);
        }
    }

    /**
     * @param iterable<SplFileInfo> $files
     *
     * @return iterable<TaskDescriptor|ContextBuilder>
     *
     * @throws \ReflectionException
     */
    private function doFindFunctions(iterable $files): iterable
    {
        $existingFunctions = get_defined_functions()['user'];

        foreach ($files as $file) {
            castor_require($file->getRealPath());

            $newExistingFunctions = get_defined_functions()['user'];

            $newFunctions = array_diff($newExistingFunctions, $existingFunctions);
            $existingFunctions = $newExistingFunctions;

            foreach ($newFunctions as $functionName) {
                $reflectionFunction = new \ReflectionFunction($functionName);
                $attributes = $reflectionFunction->getAttributes(Task::class);

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

                    yield new TaskDescriptor($taskAttribute, $reflectionFunction);

                    continue;
                }

                $attributes = $reflectionFunction->getAttributes(AsContext::class);

                if (\count($attributes) > 0) {
                    $contextAttribute = $attributes[0]->newInstance();

                    if ('' === $contextAttribute->name) {
                        $contextAttribute->name = $reflectionFunction->getShortName();
                    }

                    if ($contextAttribute->default) {
                        $contextAttribute->name = 'default';
                    }

                    yield new ContextBuilder($contextAttribute, $reflectionFunction);
                }
            }
        }
    }
}

// Don't leak internal variables
function castor_require(string $file): void
{
    require_once $file;
}
