<?php

namespace remote_import;

use Castor\Attribute\AsTask;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;

if (interface_exists(Selectable::class)) {
    class TestSelectable implements Selectable
    {
        /** @phpstan-ignore-next-line */
        public function matching(Criteria $criteria): ArrayCollection
        {
            /* @phpstan-ignore-next-line */
            return new ArrayCollection(['a', 'b', 'c']);
        }
    }
}

#[AsTask(description: 'Use a class that extends a class imported from a remote package')]
function remote_task_class(): void
{
    $selectable = new TestSelectable();
    /** @phpstan-ignore-next-line */
    $criteria = new Criteria();
    $selected = $selectable->matching($criteria);

    /* @phpstan-ignore-next-line */
    echo 'Selected: ' . implode(', ', $selected->toArray()) . \PHP_EOL;
}
