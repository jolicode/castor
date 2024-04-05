<?php

namespace Castor\Import;

use Castor\Descriptor\TaskDescriptorCollection;
use Castor\Function\FunctionLoader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** @internal */
final class Kernel
{
    /**
     * @param list<Mount> $mounts
     */
    public function __construct(
        #[Autowire(lazy: true)]
        private readonly Importer $importer,
        private readonly FunctionLoader $functionLoader,
        private array $mounts = [],
    ) {
    }

    public function addMount(Mount $mount): void
    {
        $this->mounts[] = $mount;
    }

    public function mount(): TaskDescriptorCollection
    {
        $taskDescriptorCollection = new TaskDescriptorCollection();
        foreach ($this->mounts as $mount) {
            $currentFunctions = get_defined_functions()['user'];
            $currentClasses = get_declared_classes();

            $this->importer->require($mount->path);

            $taskDescriptorCollectionTmp = $this->functionLoader->load($currentFunctions, $currentClasses);

            foreach ($taskDescriptorCollectionTmp->taskDescriptors as $descriptor) {
                $descriptor->workingDirectory = $mount->path;
                if ($mount->namespacePrefix) {
                    if ($descriptor->taskAttribute->namespace) {
                        $descriptor->taskAttribute->namespace = $mount->namespacePrefix . ':' . $descriptor->taskAttribute->namespace;
                    } else {
                        $descriptor->taskAttribute->namespace = $mount->namespacePrefix;
                    }
                }
            }

            $taskDescriptorCollection = $taskDescriptorCollection->merge($taskDescriptorCollectionTmp);
        }
        $this->mounts = [];

        return $taskDescriptorCollection;
    }
}
