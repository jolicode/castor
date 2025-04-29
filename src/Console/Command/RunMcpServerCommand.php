<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\CallToolResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\TextContent;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ToolInputSchema;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** @internal */
class RunMcpServerCommand extends Command implements SignalableCommandInterface
{
    private string $logFile;

    public function __construct(
        private readonly Application $application,
        private LoggerInterface $logger
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
        $this->logger->info('Signal received, stopping MCP server...', [
            'signal' => $signal,
            'previousExitCode' => $previousExitCode,
            'uptime' => time() - $_SERVER['REQUEST_TIME'],
        ]);

        exit($previousExitCode);
    }

    protected function configure(): void
    {
        $this
            ->setName('castor:run-mcp-server')
            ->setAliases(['run-mcp-server'])
            ->setDescription('Run an MCP server that exposes Castor tasks')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger = $this->initializeMcpLogger();
        $this->logger->info('Starting MCP server', [
            'timestamp' => date('Y-m-d H:i:s'),
            'pid' => getmypid(),
            'logFile' => $this->logFile,
        ]);

        $mcpServer = new Server('castor-mcp-server', $this->logger);
        $this->logger->debug('MCP server instance created', [
            'serverName' => 'castor-mcp-server',
        ]);

        $this->registerHandlers($mcpServer);

        $initOptions = $mcpServer->createInitializationOptions();
        $this->logger->debug('Initialization options created', [
            'options' => $initOptions,
        ]);

        $runner = new ServerRunner($mcpServer, $initOptions, $this->logger);
        $this->logger->info('Server runner initialized, starting server...');

        try {
            $this->logger->info('Running MCP server');
            $runner->run();
        } catch (\Throwable $e) {
            $this->logger->error('Server run failed', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Initialize the MCP logger with custom formatter.
     */
    private function initializeMcpLogger(): Logger
    {
        $mcpLogger = new Logger('mcp-server');

        $jsonFormatter = new JsonFormatter(
            JsonFormatter::BATCH_MODE_JSON,
            true,
        );

        $fileHandler = new StreamHandler($this->logFile, Level::Debug);
        $fileHandler->setFormatter($jsonFormatter);
        $mcpLogger->pushHandler($fileHandler);

        return $mcpLogger;
    }

    private function registerHandlers(Server $mcpServer): void
    {
        $mcpServer->registerHandler('tools/list', function () {
            $this->logger->info('Registering tools/list request handler', [
                'handler' => 'tools/list',
                'tool' => 'tools/list',
            ]);

            return $this->handleToolsList();
        });

        $mcpServer->registerHandler('tools/call', function (CallToolRequestParams $params) {
            $this->logger->info('Registering tools/call request handler', [
                'handler' => 'tools/call',
                'tool' => $params->name,
                'arguments' => $params->arguments ?? [],
            ]);

            return $this->handleToolCall($params);
        });

        $this->logger->info('MCP server handlers registered successfully');
    }

    private function handleToolsList(): ListToolsResult
    {
        $this->logger->debug('Building tools list', [
            'handler' => 'tools/list',
            'tool' => 'tools/list',
        ]);
        $tools = [];

        foreach ($this->application->all() as $command) {
            // Skip hidden commands and the MCP server command itself
            if ($command->isHidden() || $command->getName() === $this->getName() || 'castor:mcp-monitor' === $command->getName()) {
                $this->logger->debug('Skipping command', [
                    'handler' => 'tools/list',
                    'command' => $command->getName(),
                    'reason' => $command->isHidden() ? 'hidden' : 'internal',
                ]);

                continue;
            }

            $tools[] = $this->createToolFromCommand($command);
        }

        return new ListToolsResult($tools);
    }

    private function createToolFromCommand(Command $command): Tool
    {
        $properties = new ToolInputProperties();
        $required = [];

        foreach ($command->getDefinition()->getArguments() as $argument) {
            $properties->{$argument->getName()} = [
                'type' => $argument->isArray() ? 'array' : 'string',
                'description' => $argument->getDescription() ?: 'No description available',
                'isArgument' => true,
            ];

            if ($argument->isRequired()) {
                $required[] = $argument->getName();
            }

            if (null !== $argument->getDefault()) {
                $properties->{$argument->getName()} = [...$properties->{$argument->getName()},
                    'default' => $argument->getDefault()];
            }
        }

        foreach ($command->getDefinition()->getOptions() as $option) {
            $properties->{$option->getName()} = [
                'type' => $option->acceptValue()
                    ? ($option->isArray() ? 'array' : 'string')
                    : 'boolean',
                'description' => $option->getDescription() ?: 'No description available',
                'isOption' => true,
            ];

            if ($option->isValueRequired()) {
                $required[] = $option->getName();
            }

            if (null !== $option->getDefault()) {
                $properties->{$option->getName()} = [...$properties->{$option->getName()}, 'default' => $option->getDefault()];
            }
        }

        $inputSchema = new ToolInputSchema(
            properties: $properties,
            required: !empty($required) ? $required : null
        );

        $tool = new Tool(
            name: $command->getName(),
            inputSchema: $inputSchema,
            description: $command->getDescription() ?: 'No description available'
        );

        // Collect all argument and option details for a single log entry
        $argumentDetails = [];
        foreach ($command->getDefinition()->getArguments() as $argument) {
            $argumentDetails[$argument->getName()] = [
                'required' => $argument->isRequired(),
                'isArray' => $argument->isArray(),
                'hasDefault' => null !== $argument->getDefault(),
                'description' => $argument->getDescription() ?: 'No description available',
            ];
        }

        $optionDetails = [];
        foreach ($command->getDefinition()->getOptions() as $option) {
            $optionDetails[$option->getName()] = [
                'acceptsValue' => $option->acceptValue(),
                'isArray' => $option->isArray(),
                'valueRequired' => $option->isValueRequired(),
                'hasDefault' => null !== $option->getDefault(),
                'description' => $option->getDescription() ?: 'No description available',
            ];
        }

        $this->logger->debug('Adding command to tools list', [
            'handler' => 'tools/list',
            'command' => $command->getName(),
            'description' => $command->getDescription(),
            'arguments' => $argumentDetails,
            'options' => $optionDetails,
        ]);

        return $tool;
    }

    /**
     * Handle tools/call request.
     */
    private function handleToolCall(CallToolRequestParams $params): CallToolResult
    {
        $name = $params->name;
        $arguments = $params->arguments ?? [];

        try {
            return $this->executeToolCommand($name, (array) $arguments);
        } catch (\Throwable $e) {
            $this->logger->error('Error executing tool', [
                'handler' => 'tools/call',
                'tool' => $name,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'arguments' => $arguments,
            ]);

            return new CallToolResult(
                [new TextContent(text: 'Error: ' . $e->getMessage())],
                true
            );
        }
    }

    private function executeToolCommand(string $name, array $arguments): CallToolResult
    {
        $command = $this->application->find($name);

        $commandArray = $this->parseArgsAndBuildCommandArray($name, $command, $arguments);

        $output = '';
        $process = new Process($commandArray);
        $process->setTimeout(null);

        $startTime = microtime(true);
        // Generate a unique execution ID for this process
        $executionId = uniqid($name . '_', true);

        $process->run(function ($type, $buffer) use ($name, &$output, $executionId) {
            $outputType = Process::OUT === $type ? 'stdout' : 'stderr';

            // Log command output
            $this->logger->debug('Process output', [
                'handler' => 'tools/call',
                'tool' => $name,
                'type' => $outputType,
                'output' => $buffer,
                'executionId' => $executionId,
            ]);

            $output .= $buffer;
        });

        $executionTime = microtime(true) - $startTime;
        $exitCode = $process->getExitCode();
        $isError = !$process->isSuccessful();

        $this->logger->info('Process execution completed', [
            'handler' => 'tools/call',
            'tool' => $name,
            'exitCode' => $exitCode,
            'output' => $output,
            'executionTimeMs' => round($executionTime * 1000, 2),
            'success' => !$isError,
        ]);

        if ($isError) {
            $this->logger->error('Command execution failed', [
                'handler' => 'tools/call',
                'tool' => $name,
                'exitCode' => $exitCode,
                'errorOutput' => $process->getErrorOutput(),
            ]);

            if (empty($output)) {
                $output = 'Command failed with no output';
            }
        }

        $content = [
            new TextContent(
                text: trim($output) ?: $output
            ),
        ];

        return new CallToolResult($content, $isError);
    }

    private function parseArgsAndBuildCommandArray(string $name, Command $command, array $arguments): array
    {
        $commandArray = [\PHP_BINARY, $_SERVER['SCRIPT_FILENAME'], $name];
        $definition = $command->getDefinition();
        $argumentNames = array_map(fn ($arg) => $arg->getName(), $definition->getArguments());
        $optionNames = array_map(fn ($opt) => $opt->getName(), $definition->getOptions());
        

        // Process all arguments
        foreach ($arguments as $key => $value) {
            // Handle positional arguments
            if (\in_array($key, $argumentNames)) {
                $commandArray[] = $value;
                continue;
            }
            
            // Skip arguments not defined in the command
            if (!\in_array($key, $optionNames)) {
                continue;
            }
            
            // Handle options based on their value type
            $optionKey = '--' . $key;
            
            // Skip false boolean options
            if (false === $value) {
                continue;
            }
            
            // Handle flag options (true or null)
            if (true === $value || null === $value) {
                $commandArray[] = $optionKey;
                continue;
            }
            
            // Handle options with values
            $commandArray[] = $optionKey . '=' . $value;
        }
        
        // Log command preparation in a concise format
        $this->logger->debug('Command prepared', [
            'handler' => 'tools/call',
            'tool' => $name,
            'command' => [
                'executable' => basename($commandArray[0]),
                'script' => basename($commandArray[1]),
                'name' => $name,
                'args' => array_slice($commandArray, 3),
            ],
        ]);
        
        return $commandArray;
    }
}
