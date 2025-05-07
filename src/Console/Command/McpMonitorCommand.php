<?php

namespace Castor\Console\Command;

use Monolog\Level;
use Phiki\Grammar\Grammar;
use Phiki\Phiki;
use Phiki\Theme\Theme;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @internal */
class McpMonitorCommand extends Command implements SignalableCommandInterface
{
    private string $logFile;
    private bool $shouldStop = false;
    private ?int $filterLevelValue = null;
    private string $datetimeFormat = 'H:i:s';

    public function __construct(
        private readonly Phiki $phiki = new Phiki(),
    ) {
        parent::__construct();
        $this->logFile = sys_get_temp_dir() . '/castor-mcp-server.log';
    }

    public function getSubscribedSignals(): array
    {
        return [
            \SIGINT,  // Ctrl+C
            \SIGTERM, // kill
        ];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    protected function configure(): void
    {
        $this
            ->setName('castor:mcp-monitor')
            ->setAliases(['mcp-monitor'])
            ->setDescription('Monitor MCP server log file')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow output in real-time')
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Number of lines to display initially', 10)
            ->addOption('no-context', null, InputOption::VALUE_NONE, 'Disable displaying of JSON context')
            ->addOption('level', 'l', InputOption::VALUE_REQUIRED, 'Filter by log level (DEBUG, INFO, WARNING, ERROR, NOTICE, CRITICAL, ALERT, EMERGENCY) - shows all logs at or above the specified level', 'DEBUG')
            ->addOption('datetime-format', null, InputOption::VALUE_REQUIRED, 'Format for datetime display (e.g., "Y-m-d H:i:s", "H:i:s")', 'H:i:s')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $follow = $input->getOption('follow');
        $lines = (int) $input->getOption('lines');
        $noContext = $input->getOption('no-context');
        $levelName = $input->getOption('level') ? strtoupper($input->getOption('level')) : null;
        $this->filterLevelValue = $levelName ? Level::fromName(strtoupper($levelName))->value : null;
        $this->datetimeFormat = $input->getOption('datetime-format');

        if (!file_exists($this->logFile)) {
            $io->error('MCP server log file not found. Is the MCP server running?');

            return Command::FAILURE;
        }

        $this->displayLastLines($io, $lines, $noContext);

        if ($follow) {
            $this->followLogFile($io, $noContext);
        }

        return Command::SUCCESS;
    }

    /**
     * Display the last N lines from the log file.
     */
    private function displayLastLines(SymfonyStyle $io, int $lines, bool $noContext): void
    {
        $logContent = file_exists($this->logFile) ? file($this->logFile) : [];

        $logLines = \array_slice($logContent, -$lines);

        foreach ($logLines as $line) {
            $this->processAndDisplayLogLine($io, $line, $noContext);
        }
    }

    private function followLogFile(SymfonyStyle $io, bool $noContext): void
    {
        $position = filesize($this->logFile);

        while (!$this->shouldStop) {
            clearstatcache(false, $this->logFile);
            $currentSize = filesize($this->logFile);

            if ($currentSize > $position) {
                $handle = fopen($this->logFile, 'r');
                fseek($handle, $position);

                while (($line = fgets($handle)) !== false) {
                    $this->processAndDisplayLogLine($io, $line, $noContext);
                }

                $position = ftell($handle);
                fclose($handle);
            }

            usleep(100000); // Sleep for 100ms to reduce CPU usage
        }
    }

    private function processAndDisplayLogLine(SymfonyStyle $io, string $line, bool $noContext): void
    {
        $logData = json_decode($line, true);
        if (!$logData) {
            $io->writeln($line); // Not JSON, just output as is

            return;
        }

        // Skip if we're filtering by level and this log level is below the filter level
        if (null !== $this->filterLevelValue) {
            $logLevelValue = $logData['level'] ?? 0;
            $logLevel = Level::fromValue($logLevelValue);
            $filterLevel = Level::fromValue($this->filterLevelValue);

            if ($logLevel->isLowerThan($filterLevel)) {
                return;
            }
        }

        // Extract log information
        $message = $logData['message'] ?? 'No message';
        $context = $logData['context'] ?? [];
        $level = $logData['level_name'] ?? 'UNKNOWN';
        $datetime = $logData['datetime'] ?? '';

        $prefix = '<fg=yellow>[mcp-server]</>';
        if (isset($context['handler'])) {
            if ('tools/call' === $context['handler'] && isset($context['tool'])) {
                $prefix = "<fg=yellow>[tools/call]</><fg=magenta>[{$context['tool']}]</>";
            } else {
                $prefix = "<fg=yellow>[{$context['handler']}]</>";
            }
        }

        $levelColor = $this->getLevelColor($level);
        $formattedLevel = "<{$levelColor}>{$level}</>";

        $timestamp = '';
        if (!empty($datetime)) {
            $dateObj = new \DateTime($datetime);
            $timestamp = $dateObj->format($this->datetimeFormat);
        }
        $io->writeln("{$timestamp} {$formattedLevel} {$prefix} {$message}");

        // Format and display context if not empty
        if (!empty($context) && !$noContext) {
            // Remove redundant fields to make output cleaner
            unset($context['executionId'], $context['handler'], $context['tool']);
            if (isset($context['trace'])) {
                $context['trace'] = '(trace omitted)';
            }

            // Skip displaying if context is empty after removing redundant fields
            if (!empty($context)) {
                $jsonString = json_encode($context, \JSON_PRETTY_PRINT);
                $highlightedJson = $this->phiki->codeToTerminal(
                    $jsonString,
                    Grammar::Json,
                    Theme::OneDarkPro,
                );

                $io->writeln($highlightedJson);
                $io->writeln(''); // Add a blank line for readability
            }
        }
    }

    private function getLevelColor(string $level): string
    {
        return match (strtoupper($level)) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'fg=red',
            'WARNING' => 'fg=yellow',
            'INFO' => 'fg=green',
            'DEBUG' => 'fg=blue',
            default => 'fg=default',
        };
    }
}
