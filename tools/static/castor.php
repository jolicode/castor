<?php

namespace castor\static;

use Castor\Attribute\AsTask;

use function Castor\run;

// Extensions should be in sync with .github/actions/cache/action.yaml

#[AsTask(description: 'Build static binary for Linux system')]
function linux()
{
    run('bin/castor compile tools/phar/build/castor.linux-amd64.phar --os=linux --arch=x86_64 --binary-path=castor.linux-amd64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2', timeout: 0);
}

#[AsTask(description: 'Build static binary for MacOS (amd64) system')]
function darwinAmd64()
{
    run('bin/castor compile tools/phar/build/castor.darwin-amd64.phar --os=macos --arch=x86_64 --binary-path=castor.darwin-amd64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2', timeout: 0);
}

#[AsTask(description: 'Build static binary for MacOS (arm64) system')]
function darwinArm64()
{
    run('bin/castor compile tools/phar/build/castor.darwin-arm64.phar --os=macos --arch=aarch64 --binary-path=castor.darwin-arm64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2', timeout: 0);
}
