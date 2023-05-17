<?php

namespace Castor\Example;

use Castor\Attribute\AsContext;
use Castor\Attribute\Task;
use Castor\Context;

#[AsContext(name: 'production')]
function productionContext(): Context {
    return new Context(['production' => true]);
}

#[AsContext(default: true)]
function defaultContext(): Context {
    return new Context(['production' => false]);
}

#[Task(description: "A simple task that use context")]
function context(Context $context) {
    if ($context['production']) {
        echo "production\n";
    } else {
        echo "development\n";
    }
}