<?php

namespace Castor;

use Castor\Helper\HasherHelper as BaseHasherHelper;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

trigger_deprecation('castor', '0.15', 'The "%s" class is deprecated, use "%s" instead.', HasherHelper::class, BaseHasherHelper::class);

/**
 * @deprecated since castor/castor 0.15, use Castor\Helper\HasherHelper instead.
 */
#[Exclude]
class HasherHelper extends BaseHasherHelper
{
}
