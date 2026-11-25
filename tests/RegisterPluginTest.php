<?php

namespace Castor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

class RegisterPluginTest extends TaskTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        // Copied, as the path repositories lock the packages to their content
        $this->directory = sys_get_temp_dir() . '/castor-tests/register-plugin-' . uniqid();

        new Filesystem()->mirror(__DIR__ . '/fixtures/valid/remote-package-autoload', $this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAPluginExposesTheTasksItAutoloads(): void
    {
        $this->writeCastorFile("register_plugin('castor-test/package');");

        $process = $this->runTask(['package:hello'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package', $process->getOutput());
        // In the working directory of the context, not in the one of the package
        $this->assertStringContainsString('Working directory: ' . realpath($this->directory . '/registered') . "\n", $process->getOutput());
        $this->assertStringNotContainsString('castor-test/package', $process->getErrorOutput());
    }

    public function testTheCastorFileOfAPluginIsNotLoaded(): void
    {
        $this->writeCastorFile("register_plugin('castor-test/package');");

        $process = $this->runTask(['package:check'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('"package:check" is not defined', $process->getOutput() . $process->getErrorOutput());
    }

    public function testAPrefixIsPrependedToTheNamespaceOfTheTasks(): void
    {
        $this->writeCastorFile("register_plugin('castor-test/package', prefix: 'app');");

        $process = $this->runTask(['app:package:hello'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the package', $process->getOutput());
    }

    public function testAFeatureReplacesTheDefaultOne(): void
    {
        $this->writeCastorFile("register_plugin('castor-test/featured', 'qa');");

        $process = $this->runTask(['list', '--raw'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('featured:lint', $process->getOutput());
        $this->assertStringNotContainsString('featured:hello', $process->getOutput());
        // Asking for a feature registers the package
        $this->assertStringNotContainsString('castor-test/featured', $process->getErrorOutput());
    }

    public function testTheDefaultFeatureCanBeCombinedWithOthers(): void
    {
        $this->writeCastorFile(<<<'PHP'
            register_plugin('castor-test/featured', ['default', 'qa']);
            // Adding a feature later, a directory
            register_plugin('castor-test/featured', 'tools');
            // Already loaded
            register_plugin('castor-test/featured', 'qa');
            PHP);

        $process = $this->runTask(['list', '--raw'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('featured:hello', $process->getOutput());
        $this->assertStringContainsString('featured:lint', $process->getOutput());
        $this->assertStringContainsString('featured:format', $process->getOutput());
    }

    public function testAPackageCanRedefineItsDefaultFeature(): void
    {
        $this->addFeatures('package', ['default' => ['castor.php']]);
        $this->writeCastorFile("register_plugin('castor-test/package');");

        $process = $this->runTask(['list', '--raw'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('package:check', $process->getOutput());
        $this->assertStringNotContainsString('package:hello', $process->getOutput());
    }

    #[DataProvider('provideErrors')]
    public function testErrors(string $code, string $error): void
    {
        $this->writeCastorFile($code);

        $process = $this->runTask(['list'], $this->directory . '/registered', needRemote: true);

        $this->assertSame(1, $process->getExitCode());
        // Wrapped by the console
        $this->assertStringContainsString($error, preg_replace('{\s+}', ' ', str_replace(['  ', "\n"], '', $process->getErrorOutput())) ?? '');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideErrors(): iterable
    {
        yield 'unknown feature' => [
            "register_plugin('castor-test/featured', 'unknown');",
            'The plugin "castor-test/featured" has no "unknown" feature, available features: "default", "qa", "tools", "outside", "autoloaded".',
        ];
        yield 'path outside of the package' => [
            "register_plugin('castor-test/featured', 'outside');",
            'The path "../package/src/functions.php" of the "outside" feature of the plugin "castor-test/featured" is outside of the package.',
        ];
        yield 'autoloaded file' => [
            "register_plugin('castor-test/featured', 'autoloaded');",
            'The file "tasks.php" of the "autoloaded" feature of the plugin "castor-test/featured" is also in the "autoload" section of its composer.json file',
        ];
        yield 'not installed' => [
            "register_plugin('castor-test/unknown');",
            'The package "castor-test/unknown" is not installed, require it with "castor composer require castor-test/unknown".',
        ];
        yield 'two prefixes' => [
            "register_plugin('castor-test/package'); register_plugin('castor-test/package', prefix: 'app');",
            'The plugin "castor-test/package" is already registered without prefix, it cannot be registered with the prefix "app" too',
        ];
        yield 'scheme' => [
            "register_plugin('composer://castor-test/package');",
            'The plugin "composer://castor-test/package" must be the name of a package, like "vendor/package", without "composer://".',
        ];
    }

    public function testNothingIsLoadedWhenTheRemotePackagesAreDisabled(): void
    {
        $this->writeCastorFile("register_plugin('castor-test/unknown');");

        $process = $this->runTask(['list'], $this->directory . '/registered');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Could not import "castor-test/unknown": Remote imports are disabled.', $process->getErrorOutput());
    }

    public function testRequiringAPluginRegistersItInTheCastorFile(): void
    {
        $this->writeCastorFile('', 'required');
        $castorFile = $this->directory . '/required/castor.php';

        $process = $this->runTask(['composer', 'remove', 'castor-test/featured'], $this->directory . '/required', needRemote: true);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $process = $this->runTask(['composer', 'require', 'castor-test/featured:*@dev'], $this->directory . '/required', needRemote: true, env: ['SHELL_INTERACTIVE' => '1'], input: "\n");

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Added "register_plugin(\'castor-test/featured\');" to castor.php. It also declares the features "qa", "tools", "outside", "autoloaded"', preg_replace('{\s+}', ' ', $process->getErrorOutput()) ?? '');
        $this->assertStringEqualsFile($castorFile, <<<'PHP'
            <?php

            \defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

            use function Castor\register_plugin;

            register_plugin('castor-test/featured');

            PHP);

        $process = $this->runTask(['featured:hello'], $this->directory . '/required', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Hello from the featured package', $process->getOutput());

        // Removing it only reminds to remove the call
        $code = (string) file_get_contents($castorFile);
        $process = $this->runTask(['--no-remote', 'composer', 'remove', 'castor-test/featured'], $this->directory . '/required', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('The package "castor-test/featured" is no longer required: remove its "register_plugin()" call from castor.php', preg_replace('{\s+}', ' ', str_replace("\n !", "\n", $process->getErrorOutput())) ?? '');
        $this->assertStringEqualsFile($castorFile, $code);
    }

    public function testRequiringAPluginWithoutInteractionTellsHowToRegisterIt(): void
    {
        $this->writeCastorFile('', 'required');
        $castorFile = $this->directory . '/required/castor.php';
        $code = (string) file_get_contents($castorFile);

        $process = $this->runTask(['composer', 'remove', 'castor-test/featured'], $this->directory . '/required', needRemote: true);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $process = $this->runTask(['composer', 'require', 'castor-test/featured:*@dev', '--no-interaction'], $this->directory . '/required', needRemote: true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        // Without the borders of the note
        $this->assertStringContainsString('add "use function Castor\register_plugin;" and "register_plugin(\'castor-test/featured\');" to your castor.php file', preg_replace('{\s+}', ' ', str_replace("\n !", "\n", $process->getErrorOutput())) ?? '');
        $this->assertStringEqualsFile($castorFile, $code);
    }

    private function writeCastorFile(string $code, string $directory = 'registered'): void
    {
        $use = '' === $code ? '' : "\nuse function Castor\\register_plugin;\n\n" . $code . "\n";

        file_put_contents($this->directory . '/' . $directory . '/castor.php', <<<PHP
            <?php

            \\defined('CASTOR_USE_CHDIR') || \\define('CASTOR_USE_CHDIR', true);
            {$use}
            PHP);
    }

    /**
     * @param array<string, list<string>> $features
     */
    private function addFeatures(string $package, array $features): void
    {
        $file = $this->directory . '/' . $package . '/composer.json';
        $json = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        $json['extra']['castor']['features'] = $features;

        file_put_contents($file, json_encode($json, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
    }
}
