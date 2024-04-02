<?php

namespace Castor;

use Castor\Helper\HasherHelper as BaseHasherHelper;

trigger_deprecation('castor', '0.15', 'The "%s" class is deprecated, use "%s" instead.', HasherHelper::class, BaseHasherHelper::class);

/**
 * @deprecated since castor/castor 0.15, use Castor\Helper\HasherHelper instead.
 */
class HasherHelper extends BaseHasherHelper
{
}
