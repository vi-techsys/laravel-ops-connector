<?php

namespace LaravelOps\Connector\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OperationExecutor
{
    protected QueueMonitor $queueMonitor;

    public function __construct(QueueMonitor $queueMonitor)
    {
        $this->queueMonitor = $queueMonitor;
    }

    /**
     * Execute an Artisan operation.
     */
    public function execute(string $operation, array $parameters = []): array
    {
        Log::info('Connector: Executing operation', [
            'operation' => $operation,
            'parameters' => $parameters,
        ]);

        try {
            // Handle long-running daemon processes by starting them in background
            if ($operation === 'queue:work' || $operation === 'schedule:work') {
                return $this->executeInBackground($operation, $parameters);
            }

            // Handle queue monitoring operations specially
            if ($operation === 'queue:monitor' || $operation === 'queue:inspect') {
                $queuesParam = $parameters['queues'] ?? null;
                $result = $this->queueMonitor->inspect($queuesParam);
                
                return [
                    'exit_code' => $result['success'] ? 0 : 1,
                    'output' => $result['success'] ? json_encode($result['data'], JSON_PRETTY_PRINT) : ($result['error'] ?? 'Failed'),
                    'success' => $result['success'],
                ];
            }

            if ($operation === 'queue:failed-details') {
                $limit = $parameters['limit'] ?? 50;
                $offset = $parameters['offset'] ?? 0;
                $result = $this->queueMonitor->getFailedDetails($limit, $offset);
                
                return [
                    'exit_code' => $result['success'] ? 0 : 1,
                    'output' => $result['success'] ? json_encode($result['data'], JSON_PRETTY_PRINT) : ($result['error'] ?? 'Failed'),
                    'success' => $result['success'],
                ];
            }

            if ($operation === 'queue:retry-all') {
                $result = $this->queueMonitor->retryAll();
                
                return [
                    'exit_code' => $result['success'] ? 0 : 1,
                    'output' => $result['message'] ?? ($result['error'] ?? 'Failed'),
                    'success' => $result['success'],
                ];
            }

            if ($operation === 'queue:purge') {
                $queue = $parameters['queue'] ?? 'default';
                $result = $this->queueMonitor->purge($queue);
                
                return [
                    'exit_code' => $result['success'] ? 0 : 1,
                    'output' => $result['message'] ?? ($result['error'] ?? 'Failed'),
                    'success' => $result['success'],
                ];
            }

            // Handle scheduler monitoring operations
            if ($operation === 'schedule:inspect' || $operation === 'schedule:history' || $operation === 'schedule:run-task') {
                // These will be handled by SchedulerMonitor service
                // For now, return not implemented
                return [
                    'exit_code' => 1,
                    'output' => 'Scheduler monitoring operations not yet implemented',
                    'success' => false,
                ];
            }

            // Convert string parameters to appropriate types
            $convertedParameters = [];
            foreach ($parameters as $key => $value) {
                // Convert numeric strings to integers
                if (is_numeric($value)) {
                    $convertedParameters[$key] = strpos($value, '.') !== false ? (float) $value : (int) $value;
                } else {
                    $convertedParameters[$key] = $value;
                }
            }

            // For commands that expect positional arguments (like queue:forget <id>)
            // we need to pass them as indexed array values, not associative keys
            // Check if this is a command that uses positional arguments
            $positionalCommands = ['queue:forget', 'queue:retry', 'queue:retry-batch', 'config:show'];

            if (in_array($operation, $positionalCommands) && !empty($convertedParameters)) {
                // Get the first parameter value as a positional argument
                $firstParam = reset($convertedParameters);
                $exitCode = Artisan::call($operation, [$firstParam]);
            } else {
                // Execute the Artisan command with converted parameters
                $exitCode = Artisan::call($operation, $convertedParameters);
            }

            $output = Artisan::output();

            Log::info('Connector: Operation completed', [
                'operation' => $operation,
                'exit_code' => $exitCode,
                'output_length' => strlen($output),
            ]);

            return [
                'exit_code' => $exitCode,
                'output' => $this->sanitizeOutput($output),
                'success' => $exitCode === 0,
            ];

        } catch (\Exception $e) {
            Log::error('Connector: Operation failed', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sanitize output to remove sensitive information.
     */
    protected function sanitizeOutput(string $output): string
    {
        // Remove potential sensitive data patterns
        $patterns = [
            // Database credentials
            '/DB_PASSWORD=[^\s]+/i' => 'DB_PASSWORD=***',
            '/DB_USERNAME=[^\s]+/i' => 'DB_USERNAME=***',
            
            // API keys and secrets
            '/API_KEY=[^\s]+/i' => 'API_KEY=***',
            '/API_SECRET=[^\s]+/i' => 'API_SECRET=***',
            '/SECRET=[^\s]+/i' => 'SECRET=***',
            
            // Mail credentials
            '/MAIL_PASSWORD=[^\s]+/i' => 'MAIL_PASSWORD=***',
            
            // AWS credentials
            '/AWS_ACCESS_KEY_ID=[^\s]+/i' => 'AWS_ACCESS_KEY_ID=***',
            '/AWS_SECRET_ACCESS_KEY=[^\s]+/i' => 'AWS_SECRET_ACCESS_KEY=***',
        ];

        $sanitized = $output;
        foreach ($patterns as $pattern => $replacement) {
            $sanitized = preg_replace($pattern, $replacement, $sanitized);
        }

        // Limit output length to prevent memory issues
        $maxLength = 10000;
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength) . "\n... [Output truncated at {$maxLength} characters]";
        }

        return $sanitized;
    }

    /**
     * Execute a long-running operation in the background.
     */
    protected function executeInBackground(string $operation, array $parameters = []): array
    {
        try {
            // Build the Artisan command
            $artisanPath = base_path('artisan');
            $phpBinary = PHP_BINARY;
            
            // Build command parameters
            $params = '';
            if ($operation === 'queue:work') {
                $queue = $parameters['queue'] ?? null;
                $connection = $parameters['connection'] ?? null;
                
                if ($queue) {
                    $params .= ' ' . escapeshellarg($queue);
                }
                if ($connection) {
                    $params .= ' --connection=' . escapeshellarg($connection);
                }
                
                // Add common queue worker options
                $params .= ' --once'; // Process one job then exit (safer for shared hosting)
            } elseif ($operation === 'schedule:work') {
                // schedule:work doesn't need special parameters
            }
            
            // Build the full command
            $command = sprintf(
                '%s %s %s%s > /dev/null 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($artisanPath),
                $operation,
                $params
            );
            
            // For Windows, use a different approach
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // On Windows, we can't easily background processes
                // Return a message suggesting manual execution or using cron
                return [
                    'exit_code' => 1,
                    'output' => "Background worker execution is not supported on Windows servers. Please use Windows Task Scheduler or manually run: php artisan {$operation}",
                    'success' => false,
                ];
            }
            
            // Execute the command in background
            exec($command, $output, $exitCode);
            
            return [
                'exit_code' => 0,
                'output' => "Worker process started in background for: {$operation}\n\nNote: On shared hosting, consider using cron jobs instead:\n* * * * * cd " . base_path() . " && php artisan schedule:run >> /dev/null 2>&1\n* * * * * cd " . base_path() . " && php artisan queue:work --queue=default --once >> /dev/null 2>&1",
                'success' => true,
            ];
            
        } catch (\Exception $e) {
            Log::error('Connector: Background operation failed', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'exit_code' => 1,
                'output' => 'Failed to start background process: ' . $e->getMessage(),
                'success' => false,
            ];
        }
    }
}
