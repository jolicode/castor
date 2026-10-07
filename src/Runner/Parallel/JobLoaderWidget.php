<?php

namespace Castor\Runner\Parallel;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\LoaderWidget;

/**
 * A loader without the empty line LoaderWidget adds above it: the blocks of
 * all the jobs must fit on the screen to be redrawn.
 *
 * Relies on LoaderWidget::render() returning this empty line first.
 *
 * @internal
 */
final class JobLoaderWidget extends LoaderWidget
{
    public function render(RenderContext $context): array
    {
        return \array_slice(parent::render($context), -1);
    }
}
