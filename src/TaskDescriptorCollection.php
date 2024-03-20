<?php

namespace Castor;

use Castor\Descriptor\TaskDescriptorCollection as BaseTaskDescriptorCollection;

trigger_deprecation('castor', '0.15.0', 'The "%s" class is deprecated, use "%s" instead.', TaskDescriptorCollection::class, BaseTaskDescriptorCollection::class);

/**
 * @deprecated since Castor 0.15.0, use Castor\Descriptor\TaskDescriptorCollection instead
 */
class TaskDescriptorCollection extends BaseTaskDescriptorCollection
{
}
