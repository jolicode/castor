<?php

namespace Castor\Attribute;

/**
 * Calls the function, with the event as argument, when the event is
 * dispatched. Castor's events are in the Castor\Event namespace.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
class AsListener
{
    /**
     * @param string $event    The class of the event, like \Castor\Event\BeforeExecuteTaskEvent::class, or the name given to dispatch()
     * @param int    $priority The listeners with a higher priority are called first
     */
    public function __construct(
        public readonly string $event,
        public readonly int $priority = 0,
    ) {
    }
}
