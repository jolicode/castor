<?php

namespace Castor\Tests\Remote;

use Castor\GlobalHelper;
use Castor\Remote\Exception\InvalidImportUrl as InvalidImportUrlAlias;
use Castor\Remote\Exception\NotTrusted;
use Castor\Remote\Import;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

class ImportTest extends TestCase
{
    public function testInvalidScheme(): void
    {
        $this->expectException(InvalidImportUrlAlias::class);
        $this->expectExceptionMessage('The import scheme "foobar" is not supported.');

        Import::importFunctions('foobar', 'test-url', dryRun: true);
    }

    public function testInvalidGithub(): void
    {
        $this->expectException(InvalidImportUrlAlias::class);
        $this->expectExceptionMessage('The import path from GitHub repository must be formatted like this: "github://<organization>/<repository>/<function_path>@<version>".');

        Import::importFunctions('github', 'test-url', dryRun: true);
    }

    public function testValidGithubNotTrusted(): void
    {
        $this->expectException(NotTrusted::class);
        $this->expectExceptionMessage('The remote resource github.com/pyrech/castor-setup-php is not trusted.');

        GlobalHelper::setInput(new ArgvInput(['castor', '--no-trust']));
        GlobalHelper::setOutput(new NullOutput());
        GlobalHelper::setupDefaultCache();

        Import::importFunctions('github', 'pyrech/castor-setup-php/castor.php@main', dryRun: true);
    }

    public function testValidGithubTrusted(): void
    {
        GlobalHelper::setInput(new ArgvInput(['castor', '--trust']));
        GlobalHelper::setOutput(new NullOutput());
        GlobalHelper::setupDefaultCache();
        GlobalHelper::setLogger(new Logger('test'));

        $path = Import::importFunctions('github', 'pyrech/castor-setup-php/castor.php@main', dryRun: true);

        $this->assertStringContainsString('.castor/remote/github.com/pyrech/castor-setup-php/main/castor.php', $path);
    }
}
