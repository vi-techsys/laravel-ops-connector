<?php

namespace LaravelOps\Connector\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OperationExecutor
{
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
            $positionalCommands = ['queue:forget', 'queue:retry', 'queue:retry-batch', 'queue:monitor', 'config:show'];
            
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
}
