<?php

namespace mounted;

use Castor\Attribute\AsTask;
use Doctrine\Common\Collections\ArrayCollection;

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

#[AsTask(description: 'Uses a class of a package of the castor.composer.json of the mounted directory')]
function collection(): void
{
    echo new ArrayCollection(['a', 'b', 'c'])->count() . " items\n";
}
