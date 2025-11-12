<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Config;
use Castor\ConfigFlag;
use Castor\Context;

use function Castor\context;
use function Castor\io;

#[AsContext(name: 'context_with_config_flags')]
function create_context_with_config_flags(): Context
{
    return new Context(
        config: (new Config())
            ->withEnabled(ConfigFlag::ContextAwareFilesystem)
        // You can also disable flags explicitly
        // ->withDisabled(ConfigFlag::ContextAwareFilesystem)
    );
}

#[AsTask]
function use_context_with_config_flags(): void
{
    $context = context('context_with_config_flags');

    // You can get all flags and their values
    $flags = $context->config->getFlags();
    foreach ($flags as $flagName => $flagValue) {
        io()->writeln("Flag: {$flagName}, Value: " . var_export($flagValue, true));
    }

    // Or check specific flag if necessary for your logic
    if ($context->config->isEnabled(ConfigFlag::ContextAwareFilesystem)) {
        io()->writeln('ContextAwareFilesystem is enabled.');
    } else {
        io()->writeln('ContextAwareFilesystem is disabled.');
    }

    io()->newLine();
    io()->writeln('Disabling ContextAwareFilesystem flag...');
    $context->config->withDisabled(ConfigFlag::ContextAwareFilesystem);

    if ($context->config->isEnabled(ConfigFlag::ContextAwareFilesystem)) {
        io()->writeln('ContextAwareFilesystem is enabled.');
    } else {
        io()->writeln('ContextAwareFilesystem is disabled.');
    }
}
