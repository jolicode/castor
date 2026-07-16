<?php

namespace Castor\Tests;

use Castor\Runner\SshRunner;
use PHPUnit\Framework\TestCase;
use Spatie\Ssh\Ssh;

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

    /**
     * @param array{
     *     'enable_strict_check'?: bool,
     *     'password_authentication'?: bool,
     * } $sshOptions
     */
    private function buildSshCommand(array $sshOptions): string
    {
        // buildSsh() does not use the constructor dependencies, so we can skip them.
        $runner = new \ReflectionClass(SshRunner::class)->newInstanceWithoutConstructor();
        $ssh = new \ReflectionMethod(SshRunner::class, 'buildSsh')->invoke($runner, 'server-1.example.com', 'debian', $sshOptions);

        $this->assertInstanceOf(Ssh::class, $ssh);

        return $ssh->getExecuteCommand('ls');
    }
}
