#!/bin/sh

# Install composer dependencies
composer install --no-interaction --no-progress --no-suggest

# Install tools dependencies
cd tools/php-cs-fixer && composer install --no-interaction --no-progress --no-suggest && cd ../..
cd tools/phpstan && composer install --no-interaction --no-progress --no-suggest && cd ../..

# Keep container running
tail -f /dev/null
