<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CapabilityController extends Controller
{
    /**
     * Return list of supported capabilities.
     */
    public function index(): JsonResponse
    {
        $allowedOperations = config('connector.allowed_operations', []);
        $operationsEnabled = config('connector.enable_operations', true);

        // Build detailed operation list with metadata
        $operationsList = [];
        if ($operationsEnabled) {
            foreach ($allowedOperations as $operation) {
                $operationsList[$operation] = [
                    'name' => $this->getOperationName($operation),
                    'description' => $this->getOperationDescription($operation),
                    'category' => $this->getOperationCategory($operation),
                    'risk_level' => $this->getOperationRiskLevel($operation),
                    'requires_confirmation' => $this->requiresConfirmation($operation),
                    'parameters' => $this->getOperationParameters($operation),
                ];
            }
        }

        return response()->json([
            'connector_version' => '1.0.0',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => config('app.env'),
            'operations_enabled' => $operationsEnabled,
            'capabilities' => [
                'health_check' => true,
                'capability_discovery' => true,
                'operations' => $operationsEnabled,
            ],
            'supported_operations' => $operationsList,
            'features' => $this->detectFeatures(),
        ]);
    }

    /**
     * Get operation display name.
     */
    protected function getOperationName(string $operation): string
    {
        $names = [
            // Cache Operations
            'cache:clear' => 'Clear Application Cache',
            'cache:forget' => 'Forget Cache Item',
            'config:clear' => 'Clear Config Cache',
            'route:clear' => 'Clear Route Cache',
            'view:clear' => 'Clear View Cache',
            'event:clear' => 'Clear Event Cache',
            'optimize:clear' => 'Clear All Caches',
            'optimize' => 'Optimize Application',
            
            // Database Operations
            'migrate:status' => 'Migration Status',
            'migrate' => 'Run Migrations',
            'migrate:rollback' => 'Rollback Last Migration',
            'migrate:refresh' => 'Refresh Migrations',
            'migrate:reset' => 'Reset All Migrations',
            'db:show' => 'Database Information',
            'db:table' => 'Database Table Information',
            'db:wipe' => 'Drop All Tables',
            
            // Queue Operations
            'queue:clear' => 'Clear Queue',
            'queue:failed' => 'List Failed Queue Jobs',
            'queue:flush' => 'Flush Failed Queue Jobs',
            'queue:forget' => 'Delete Failed Queue Job',
            'queue:monitor' => 'Monitor Queue Sizes',
            'queue:restart' => 'Restart Queue Workers',
            'queue:retry' => 'Retry Failed Queue Job',
            'queue:retry-batch' => 'Retry Batch of Failed Queue Jobs',
            'queue:work' => 'Start Queue Worker',
            
            // Schedule Operations
            'schedule:list' => 'List Scheduled Tasks',
            'schedule:run' => 'Run Scheduled Tasks',
            'schedule:test' => 'Run A Scheduled Command',
            'schedule:work' => 'Start The Schedule Worker',
            
            // Storage Operations
            'storage:link' => 'Create Storage Symlink',
            
            // Application Operations
            'down' => 'Put Application in Maintenance Mode',
            'up' => 'Bring Application Out of Maintenance Mode',
            'about' => 'Display Environment Information',
            'env' => 'Display Current Environment',
            'key:generate' => 'Generate Application Key',
            
            // Package Operations
            'package:discover' => 'Rebuild Cached Package Manifest',
            
            // Auth Operations
            'auth:clear-resets' => 'Flush Expired Password Reset Tokens',
            
            // Permission Operations
            'permission:cache-reset' => 'Clear Permission Cache',
            
            // Diagnostic Operations
            'route:list' => 'List All Routes',
            'env' => 'Display Current Environment',
            'about' => 'Display Environment Information',
            
            // Log Operations
            'log:clear' => 'Clear Log Files',
            'log:view' => 'View Log Files',
            'log:statistics' => 'View Log Statistics',
            
            // Queue Monitoring Operations
            'queue:inspect' => 'Inspect Queue Status',
            'queue:failed-details' => 'Get Failed Job Details',
            'queue:retry-all' => 'Retry All Failed Jobs',
            'queue:purge' => 'Purge Queue Jobs',
            
            // Scheduler Monitoring Operations
            'schedule:inspect' => 'Inspect Scheduled Tasks',
            'schedule:history' => 'View Scheduler History',
            'schedule:run-task' => 'Run Scheduled Task',
        ];

        return $names[$operation] ?? ucfirst(str_replace(':', ' ', $operation));
    }

    /**
     * Get operation description.
     */
    protected function getOperationDescription(string $operation): string
    {
        $descriptions = [
            // Cache Operations
            'cache:clear' => 'Flush the application cache',
            'cache:forget' => 'Remove an item from the cache',
            'config:clear' => 'Remove the configuration cache file',
            'route:clear' => 'Remove the route cache file',
            'view:clear' => 'Clear all compiled view files',
            'event:clear' => 'Clear all cached events and listeners',
            'optimize:clear' => 'Remove all cached files (cache, config, route, view, event)',
            'optimize' => 'Cache the framework bootstrap files for better performance',
            
            // Database Operations
            'migrate:status' => 'Show the status of each migration',
            'migrate' => 'Run all pending database migrations',
            'migrate:rollback' => 'Rollback the last database migration batch',
            'migrate:refresh' => 'Reset and re-run all migrations',
            'migrate:reset' => 'Rollback all database migrations',
            'db:show' => 'Display information about the given database',
            'db:table' => 'Display information about the given database table',
            'db:wipe' => 'Drop all tables, views, and types from the database',
            
            // Queue Operations
            'queue:clear' => 'Delete all jobs from the specified queue',
            'queue:failed' => 'List all failed queue jobs',
            'queue:flush' => 'Flush all failed queue jobs',
            'queue:forget' => 'Delete a specific failed queue job by ID',
            'queue:monitor' => 'Monitor the size of the specified queues',
            'queue:restart' => 'Restart queue worker daemons after their current job',
            'queue:retry' => 'Retry a failed queue job',
            'queue:retry-batch' => 'Retry a batch of failed queue jobs by ID',
            'queue:work' => 'Start processing jobs on the queue',
            
            // Schedule Operations
            'schedule:list' => 'List the defined scheduled tasks',
            'schedule:run' => 'Run the scheduled commands',
            'schedule:test' => 'Run a scheduled command',
            'schedule:work' => 'Start the schedule worker',
            
            // Storage Operations
            'storage:link' => 'Create a symbolic link from "public/storage" to "storage/app/public"',
            
            // Application Operations
            'down' => 'Put the application into maintenance mode',
            'up' => 'Bring the application out of maintenance mode',
            'about' => 'Display basic information about your application',
            'env' => 'Display the current environment for your application',
            'key:generate' => 'Set the application key',
            
            // Package Operations
            'package:discover' => 'Rebuild the cached package manifest',
            
            // Auth Operations
            'auth:clear-resets' => 'Flush expired password reset tokens',
            
            // Permission Operations
            'permission:cache-reset' => 'Reset the permission cache (useful when using Spatie Permission package)',
            
            // Diagnostic Operations
            'route:list' => 'List all registered routes',
            'env' => 'Display the current environment for your application',
            'about' => 'Display basic information about your application',
            
            // Log Operations
            'log:clear' => 'Clear all log files',
            'log:view' => 'View recent log entries with filtering',
            'log:statistics' => 'View log statistics and summary',
        ];

        return $descriptions[$operation] ?? "Execute {$operation}";
    }

    /**
     * Get operation category.
     */
    protected function getOperationCategory(string $operation): string
    {
        if (str_contains($operation, 'cache:') || str_contains($operation, 'clear')) {
            return 'cache';
        }
        if (str_starts_with($operation, 'migrate:') || str_starts_with($operation, 'db:')) {
            return 'database';
        }
        if (str_starts_with($operation, 'queue:')) {
            return 'queue';
        }
        if (str_starts_with($operation, 'schedule:')) {
            return 'scheduler';
        }
        if (str_starts_with($operation, 'storage:')) {
            return 'storage';
        }
        if (str_starts_with($operation, 'auth:')) {
            return 'authentication';
        }
        if (str_starts_with($operation, 'permission:')) {
            return 'permissions';
        }
        if (str_starts_with($operation, 'package:')) {
            return 'packages';
        }
        if (in_array($operation, ['down', 'up', 'about', 'env'])) {
            return 'application';
        }
        return 'other';
    }

    /**
     * Get operation risk level.
     */
    protected function getOperationRiskLevel(string $operation): string
    {
        $riskLevels = [
            // Safe operations (read-only or cache clearing)
            'cache:clear' => 'low',
            'cache:forget' => 'low',
            'config:clear' => 'low',
            'route:clear' => 'low',
            'view:clear' => 'low',
            'event:clear' => 'low',
            'optimize:clear' => 'low',
            'optimize' => 'low',
            'migrate:status' => 'none',
            'db:show' => 'none',
            'db:table' => 'none',
            'queue:failed' => 'none',
            'queue:flush' => 'low',
            'schedule:list' => 'none',
            'about' => 'none',
            'env' => 'none',
            'route:list' => 'none',
            'log:clear' => 'medium',
            'log:view' => 'none',
            'log:statistics' => 'none',
            
            // Medium risk operations
            'queue:clear' => 'medium',
            'queue:restart' => 'medium',
            'queue:retry' => 'medium',
            'schedule:run' => 'medium',
            'schedule:test' => 'medium',
            'schedule:work' => 'medium',
            'storage:link' => 'medium',
            'package:discover' => 'medium',
            'auth:clear-resets' => 'medium',
            'permission:cache-reset' => 'low',
            'migrate' => 'medium',
            'migrate:rollback' => 'medium',
            'key:generate' => 'medium',
            
            // High risk operations
            'down' => 'high',
            'up' => 'low',
            'migrate:refresh' => 'high',
            'migrate:reset' => 'high',
            'db:wipe' => 'high',
        ];

        return $riskLevels[$operation] ?? 'medium';
    }

    /**
     * Check if operation requires confirmation.
     */
    protected function requiresConfirmation(string $operation): bool
    {
        $requiresConfirmation = [
            'down', // Maintenance mode
            'queue:clear', // Clear all jobs
            'queue:flush', // Flush all failed jobs
            'queue:retry-batch', // Retry batch
            'schedule:run', // Run all scheduled tasks
            'migrate', // Run migrations
            'migrate:rollback', // Rollback migrations
            'migrate:refresh', // Refresh migrations
            'migrate:reset', // Reset migrations
            'db:wipe', // Drop all tables
            'log:clear', // Clear log files
        ];

        return in_array($operation, $requiresConfirmation);
    }

    /**
     * Get operation parameters (for operations that require arguments).
     */
    protected function getOperationParameters(string $operation): array
    {
        $parameters = [
            // Queue operations requiring ID
            'queue:forget' => [
                ['name' => 'id', 'type' => 'number', 'required' => true, 'description' => 'Failed job ID to delete'],
            ],
            'queue:retry' => [
                ['name' => 'id', 'type' => 'number', 'required' => true, 'description' => 'Failed job ID to retry'],
            ],
            'queue:retry-batch' => [
                ['name' => 'id', 'type' => 'text', 'required' => true, 'description' => 'Batch ID to retry'],
            ],
            'queue:monitor' => [
                ['name' => 'queues', 'type' => 'text', 'required' => true, 'description' => 'Comma-separated queue names with connection (e.g., database:default,database:high)'],
            ],
            'queue:work' => [
                ['name' => 'queue', 'type' => 'text', 'required' => false, 'description' => 'Queue name to process (e.g., default, high)'],
                ['name' => 'connection', 'type' => 'text', 'required' => false, 'description' => 'Queue connection (e.g., database, redis)'],
            ],

            // Config operations
            'config:show' => [
                ['name' => 'config', 'type' => 'text', 'required' => true, 'description' => 'Config key to display (e.g., app.name)'],
            ],
        ];

        return $parameters[$operation] ?? [];
    }

    /**
     * Detect available features based on Laravel version and configuration.
     */
    protected function detectFeatures(): array
    {
        $features = [
            'cache_operations' => true,
            'config_diagnostics' => true,
            'route_diagnostics' => true,
        ];

        // Check Laravel version for specific features
        $laravelVersion = app()->version();
        
        if (version_compare($laravelVersion, '10.0.0', '>=')) {
            $features['migration_status'] = true;
            $features['queue_monitoring'] = true;
            $features['scheduler_monitoring'] = true;
        }

        // Check if queue is configured
        $features['queue_available'] = config('queue.default') !== 'sync';

        // Check if scheduler is configured
        $features['scheduler_configured'] = $this->isSchedulerConfigured();

        return $features;
    }

    /**
     * Check if the scheduler has any tasks configured.
     */
    protected function isSchedulerConfigured(): bool
    {
        try {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            return count($schedule->events()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
