<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class LogAllLevelTest extends TaskTestCase
{
    // log:all-level
    public function testLogAllLevel(): void
    {
        $process = $this->runTask(['log:all-level']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Re-run with -v, -vv, -vvv for different output.', $process->getOutput());
        $this->assertStringContainsString('EMERGENCY [castor] level: emergency', $process->getErrorOutput());
        $this->assertStringContainsString('ALERT     [castor] level: alert', $process->getErrorOutput());
        $this->assertStringContainsString('CRITICAL  [castor] level: critical', $process->getErrorOutput());
        $this->assertStringContainsString('ERROR     [castor] level: error', $process->getErrorOutput());
        $this->assertStringContainsString('WARNING   [castor] level: warning', $process->getErrorOutput());
        $this->assertStringNotContainsString('NOTICE    [castor] level: notice', $process->getErrorOutput());
        $this->assertStringNotContainsString('INFO      [castor] level: info', $process->getErrorOutput());
        $this->assertStringNotContainsString('DEBUG     [castor] level: debug', $process->getErrorOutput());
    }

    // log:all-level -v
    public function testLogAllLevel2(): void
    {
        $process = $this->runTask(['log:all-level', '-v']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('EMERGENCY [castor] level: emergency', $process->getErrorOutput());
        $this->assertStringContainsString('ALERT     [castor] level: alert', $process->getErrorOutput());
        $this->assertStringContainsString('CRITICAL  [castor] level: critical', $process->getErrorOutput());
        $this->assertStringContainsString('ERROR     [castor] level: error', $process->getErrorOutput());
        $this->assertStringContainsString('WARNING   [castor] level: warning', $process->getErrorOutput());
        $this->assertStringContainsString('NOTICE    [castor] level: notice', $process->getErrorOutput());
        $this->assertStringNotContainsString('INFO      [castor] level: info', $process->getErrorOutput());
        $this->assertStringNotContainsString('DEBUG     [castor] level: debug', $process->getErrorOutput());
    }

    // log:all-level -vv
    public function testLogAllLevel3(): void
    {
        $process = $this->runTask(['log:all-level', '-vv']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('EMERGENCY [castor] level: emergency', $process->getErrorOutput());
        $this->assertStringContainsString('ALERT     [castor] level: alert', $process->getErrorOutput());
        $this->assertStringContainsString('CRITICAL  [castor] level: critical', $process->getErrorOutput());
        $this->assertStringContainsString('ERROR     [castor] level: error', $process->getErrorOutput());
        $this->assertStringContainsString('WARNING   [castor] level: warning', $process->getErrorOutput());
        $this->assertStringContainsString('NOTICE    [castor] level: notice', $process->getErrorOutput());
        $this->assertStringContainsString('INFO      [castor] level: info', $process->getErrorOutput());
        $this->assertStringNotContainsString('DEBUG     [castor] level: debug', $process->getErrorOutput());
    }

    // log:all-level -vvv
    public function testLogAllLevel4(): void
    {
        $process = $this->runTask(['log:all-level', '-vvv']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('EMERGENCY [castor] level: emergency', $process->getErrorOutput());
        $this->assertStringContainsString('ALERT     [castor] level: alert', $process->getErrorOutput());
        $this->assertStringContainsString('CRITICAL  [castor] level: critical', $process->getErrorOutput());
        $this->assertStringContainsString('ERROR     [castor] level: error', $process->getErrorOutput());
        $this->assertStringContainsString('WARNING   [castor] level: warning', $process->getErrorOutput());
        $this->assertStringContainsString('NOTICE    [castor] level: notice', $process->getErrorOutput());
        $this->assertStringContainsString('INFO      [castor] level: info', $process->getErrorOutput());
        $this->assertStringContainsString('DEBUG     [castor] level: debug', $process->getErrorOutput());
    }
}
