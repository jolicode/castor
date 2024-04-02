<?php

namespace Castor;

use Castor\Helper\PathHelper as BasePathHelper;

trigger_deprecation('castor', '0.15', 'The "%s" class is deprecated, use "%s" instead.', PathHelper::class, BasePathHelper::class);

/**
 * @deprecated since castor/castor 0.15, use Castor\Helper\PathHelper instead.
 */
class PathHelper extends BasePathHelper
{
}
