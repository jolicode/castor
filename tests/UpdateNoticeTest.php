<?php

namespace Castor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

class UpdateNoticeTest extends TaskTestCase
{
    private const string FIXTURE = '{{ base }}/tests/fixtures/valid/context-file';
    private const string NOTICE = 'A new Castor version is available';

    public function testTheNoticeIsDisplayed(): void
    {
        $process = $this->runWithNewRelease(['list']);

        $this->assertStringContainsString(self::NOTICE, $process->getOutput());
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('provideCommandsWithoutNotice')]
    public function testTheNoticeIsNotDisplayed(array $args, string $expectedOutput): void
    {
        $process = $this->runWithNewRelease($args);

        $this->assertStringContainsString($expectedOutput, $process->getOutput());
        $this->assertStringNotContainsString(self::NOTICE, $process->getOutput());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideCommandsWithoutNotice(): iterable
    {
        // The shell loads the output of the completion script
        yield 'completion script' => [['completion', 'bash'], 'complete -F'];
        yield 'completion script, abbreviated' => [['compl', 'bash'], 'complete -F'];
        // The notice tells to run it
        yield 'self-update' => [['self-update', '--rollback'], 'No backup found'];
        yield 'self-update, abbreviated' => [['self', '--rollback'], 'No backup found'];
    }

    /**
     * @param list<string> $args
     */
    private function runWithNewRelease(array $args): Process
    {
        return $this->runTask($args, self::FIXTURE, env: [
            // phpunit.xml disables it for all the other tests
            'CASTOR_DISABLE_VERSION_CHECK' => false,
            // A fake release v99.0.0, see release.php
            'CASTOR_RELEASES_URL' => $_SERVER['ENDPOINT'] . '/self-update/release.php',
        ]);
    }
}
