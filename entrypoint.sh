#!/bin/sh

# Install composer dependencies
composer install --no-interaction --no-progress --no-suggest

# Keep container running
tail -f /dev/null
