<?php

namespace Castor\Tests\Import;

use Castor\Import\Exception\ImportError;
use Castor\Import\PluginRegistrationWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PluginRegistrationWriterTest extends TestCase
{
    #[DataProvider('provideCode')]
    public function testAdd(string $code, string $expected): void
    {
        $this->assertSame($expected, new PluginRegistrationWriter()->add($code, 'vendor/package'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideCode(): iterable
    {
        yield 'after the CASTOR_USE_CHDIR definition' => [
            <<<'PHP'
                <?php

                \defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

                PHP,
            <<<'PHP'
                <?php

                \defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

                use function Castor\register_plugin;

                register_plugin('vendor/package');

                PHP,
        ];

        yield 'only the opening tag' => [
            "<?php\n",
            <<<'PHP'
                <?php

                use function Castor\register_plugin;

                register_plugin('vendor/package');

                PHP,
        ];

        yield 'among the imports of Castor functions, in alphabetical order' => [
            <<<'PHP'
                <?php

                namespace App;

                use Castor\Attribute\AsTask;

                use function Castor\io;
                use function Castor\run;

                #[AsTask]
                function foo(): void
                {
                }

                PHP,
            <<<'PHP'
                <?php

                namespace App;

                use Castor\Attribute\AsTask;

                use function Castor\io;
                use function Castor\register_plugin;
                use function Castor\run;

                #[AsTask]
                function foo(): void
                {
                }

                register_plugin('vendor/package');

                PHP,
        ];

        yield 'after the imports of classes' => [
            <<<'PHP'
                <?php

                use Castor\Attribute\AsTask;

                function foo(): void
                {
                }
                PHP,
            <<<'PHP'
                <?php

                use Castor\Attribute\AsTask;

                use function Castor\register_plugin;

                function foo(): void
                {
                }

                register_plugin('vendor/package');

                PHP,
        ];

        yield 'after the namespace, before the comments of the first statement' => [
            <<<'PHP'
                <?php

                namespace App;

                /** A function */
                function foo(): void
                {
                }

                PHP,
            <<<'PHP'
                <?php

                namespace App;

                use function Castor\register_plugin;

                /** A function */
                function foo(): void
                {
                }

                register_plugin('vendor/package');

                PHP,
        ];

        yield 'already imported in a group' => [
            <<<'PHP'
                <?php

                use function Castor\{io, register_plugin};

                io();

                PHP,
            <<<'PHP'
                <?php

                use function Castor\{io, register_plugin};

                io();

                register_plugin('vendor/package');

                PHP,
        ];
    }

    #[DataProvider('provideUnsupportedCode')]
    public function testUnsupportedCode(string $code, string $reason): void
    {
        $this->expectException(ImportError::class);
        $this->expectExceptionMessage($reason);

        new PluginRegistrationWriter()->add($code, 'vendor/package');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUnsupportedCode(): iterable
    {
        yield 'braced namespace' => ["<?php\nnamespace App {\n}\n", 'its namespace uses braces'];
        yield 'several namespaces' => ["<?php\nnamespace A;\nnamespace B;\n", 'it declares several namespaces'];
        yield 'closing tag' => ["<?php\nfoo();\n?>\n", 'it ends with a closing "?>" tag'];
        yield 'syntax error' => ["<?php\nfoo(\n", 'it cannot be parsed'];
    }

    public function testIsRegistered(): void
    {
        $writer = new PluginRegistrationWriter();

        $this->assertTrue($writer->isRegistered("<?php\nregister_plugin('vendor/package');", 'vendor/package'));
        $this->assertTrue($writer->isRegistered("<?php\n\\Castor\\register_plugin('Vendor/Package', 'qa');", 'vendor/package'));
        $this->assertFalse($writer->isRegistered("<?php\nregister_plugin('vendor/other');", 'vendor/package'));
        $this->assertFalse($writer->isRegistered("<?php\nimport('composer://vendor/package');", 'vendor/package'));
        $this->assertFalse($writer->isRegistered("<?php\nfoo(", 'vendor/package'));
    }
}
