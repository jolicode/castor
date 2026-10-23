<?php

namespace Castor\Tests\Console\Input;

use Castor\Console\Application;
use Castor\Console\Input\CommandLine;
use Castor\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;

class CommandLineTest extends TestCase
{
    private static Application $application;

    public static function setUpBeforeClass(): void
    {
        // The application of Castor, with its global options and built-in commands
        $kernel = new Kernel('test', false, __DIR__, true, null, false);
        $kernel->boot();

        $application = $kernel->getContainer()->get(Application::class);
        self::assertInstanceOf(Application::class, $application);
        self::$application = $application;
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('provideSelfUpdate')]
    public function testSelfUpdateIsRecognized(array $argv, bool $expected): void
    {
        $this->assertSame($expected, $this->createCommandLine($argv)->isSelfUpdate());
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function provideSelfUpdate(): iterable
    {
        yield 'name' => [['castor:self-update'], true];
        yield 'alias, with an option of the command' => [['self-update', '--rollback'], true];
        yield 'after an option with a value' => [['--castor-file', 'my-file.php', 'self-update', '--rollback'], true];
        yield 'after an option with an equal sign' => [['--castor-file=my-file.php', 'self-update'], true];
        yield 'after a flag' => [['-v', 'self-update'], true];
        // The context option is not declared yet
        yield 'after a context' => [['-c', 'production', 'self-update'], true];
        yield 'after a context, with the long option' => [['--context', 'production', 'self-update'], true];
        yield 'as the value of an option' => [['--castor-file', 'self-update', 'list'], false];
        yield 'as an argument' => [['help', 'self-update'], false];
        // It could be a task, which is not loaded yet
        yield 'abbreviation' => [['self'], false];
    }

    public function testTheShellCompletionIsRecognized(): void
    {
        $commandLine = $this->createCommandLine(['_complete', '--no-interaction', '-sbash', '-c1', '-a1', '-icastor', '-il']);

        $this->assertTrue($commandLine->isShellCompletion());
        $this->assertTrue($commandLine->isOutputMachineReadable());
        // "-c" is the position of the cursor
        $this->assertNull($commandLine->getContextName());
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('provideOutputMachineReadable')]
    public function testTheOutputIsMachineReadable(array $argv, bool $expected): void
    {
        $this->assertSame($expected, $this->createCommandLine($argv)->isOutputMachineReadable());
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function provideOutputMachineReadable(): iterable
    {
        yield 'completion script' => [['completion', 'bash'], true];
        yield 'format with an equal sign' => [['list', '--format=json'], true];
        yield 'format with a value' => [['help', 'list', '--format', 'xml'], true];
        yield 'text format' => [['list', '--format', 'txt'], false];
        yield 'no format' => [['list'], false];
        yield 'format given to the task' => [['my-task', '--', '--format', 'json'], false];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('provideHiddenCommandsDescribed')]
    public function testTheHiddenCommandsAreDescribed(array $argv, bool $expected): void
    {
        $this->assertSame($expected, $this->createCommandLine($argv)->describesHiddenCommands());
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function provideHiddenCommandsDescribed(): iterable
    {
        yield 'list in JSON' => [['list', '--format=json'], true];
        yield 'list in XML' => [['list', '--format', 'xml'], true];
        yield 'list in Markdown' => [['list', '--format=md'], false];
        yield 'list in text' => [['list'], false];
        // Only the command given is described
        yield 'help in JSON' => [['help', 'list', '--format=json'], false];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('provideContextName')]
    public function testTheContextIsRead(array $argv, ?string $expected): void
    {
        $this->assertSame($expected, $this->createCommandLine($argv)->getContextName());
    }

    /**
     * @return iterable<string, array{list<string>, ?string}>
     */
    public static function provideContextName(): iterable
    {
        yield 'long option' => [['my-task', '--context', 'production'], 'production'];
        yield 'long option with an equal sign' => [['my-task', '--context=production'], 'production'];
        yield 'shortcut before the command' => [['-c', 'production', 'my-task'], 'production'];
        yield 'shortcut with the value attached' => [['my-task', '-cproduction'], 'production'];
        yield 'given to the task' => [['my-task', '--', '-c', 'production'], null];
        yield 'none' => [['my-task'], null];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('provideRemoteOptions')]
    public function testTheRemoteOptionsAreRead(array $argv, bool $noRemote, bool $updateRemotes): void
    {
        $commandLine = $this->createCommandLine($argv);

        $this->assertSame($noRemote, $commandLine->hasNoRemote());
        $this->assertSame($updateRemotes, $commandLine->hasUpdateRemotes());
    }

    /**
     * @return iterable<string, array{list<string>, bool, bool}>
     */
    public static function provideRemoteOptions(): iterable
    {
        yield 'no remote' => [['--no-remote', 'my-task'], true, false];
        yield 'update remotes' => [['my-task', '--update-remotes'], false, true];
        yield 'given to the task' => [['my-task', '--', '--no-remote', '--update-remotes'], false, false];
    }

    public function testTheInputIsLeftUntouched(): void
    {
        $input = new ArgvInput(['castor', '--castor-file', 'my-file.php', 'self-update']);

        new CommandLine($input, self::$application);

        // The castor file reads it while it is loaded
        $this->assertFalse($input->hasOption('castor-file'));
    }

    /**
     * @param list<string> $argv
     */
    private function createCommandLine(array $argv): CommandLine
    {
        return new CommandLine(new ArgvInput(['castor', ...$argv]), self::$application);
    }
}
