<?php

namespace castor\static;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

// Extensions should be in sync with .github/actions/cache/action.yaml

#[AsTask(description: 'Build static binary for Linux (amd64) system')]
function linuxAmd64()
{
    run('bin/castor compile tools/phar/build/castor.linux-amd64.phar --os=linux --arch=x86_64 --binary-path=castor.linux-amd64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2,iconv', context: context()->withTimeout(0));
}

#[AsTask(description: 'Build static binary for Linux (arm64) system')]
function linuxArm64()
{
    run('bin/castor compile tools/phar/build/castor.linux-arm64.phar --os=linux --arch=aarch64 --binary-path=castor.linux-arm64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2,iconv', context: context()->withTimeout(0));
}

#[AsTask(description: 'Build static binary for MacOS (amd64) system')]
function darwinAmd64()
{
    run('bin/castor compile tools/phar/build/castor.darwin-amd64.phar --os=macos --arch=x86_64 --binary-path=castor.darwin-amd64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2,iconv', context: context()->withTimeout(0));
}

#[AsTask(description: 'Build static binary for MacOS (arm64) system')]
function darwinArm64()
{
    run('bin/castor compile tools/phar/build/castor.darwin-arm64.phar --os=macos --arch=aarch64 --binary-path=castor.darwin-arm64 --php-extensions=mbstring,phar,posix,tokenizer,pcntl,curl,filter,openssl,sodium,ctype,zip,bz2,iconv', context: context()->withTimeout(0));
}

#[AsTask(description: 'Build static binary for Windows (amd64) system')]
function windowsAmd64()
{
    // No posix nor pcntl extensions on Windows
    run('php bin/castor compile tools/phar/build/castor.windows-amd64.phar --os=windows --arch=x86_64 --binary-path=castor.windows-amd64.exe --php-extensions=mbstring,phar,tokenizer,curl,filter,openssl,sodium,ctype,zip,bz2,iconv', context: context()->withTimeout(0));
}
