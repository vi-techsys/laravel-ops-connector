<?php

namespace LaravelOps\Connector\Services;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class SchedulerMonitor
{
    /**
     * Inspect scheduled tasks.
     */
    public function inspect(): array
    {
        try {
            $schedule = app(Schedule::class);
            $events = $schedule->events();

            $tasks = [];
            foreach ($events as $event) {
                $tasks[] = [
                    'command' => $event->command ?? 'N/A',
                    'expression' => $event->expression ?? 'N/A',
                    'description' => $event->description ?? 'No description',
                    'next_run' => $this->getNextRunTime($event),
                    'timezone' => $event->timezone ?? config('app.timezone', 'UTC'),
                    'without_overlapping' => $event->withoutOverlapping ?? false,
                    'on_one_server' => $event->onOneServer ?? false,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'tasks' => $tasks,
                    'total' => count($tasks),
                    'cron_configured' => $this->isCronConfigured(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Schedule inspection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get scheduler execution history (from cache/database).
     */
    public function getHistory(int $limit = 50): array
    {
        try {
            // Check if schedule_history table exists
            if (!$this->historyTableExists()) {
                return [
                    'success' => true,
                    'data' => [
                        'history' => [],
                        'message' => 'No execution history available. Configure schedule logging to track execution history.',
                    ],
                ];
            }

            $history = \DB::table('schedule_history')
                ->orderBy('executed_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'data' => [
                    'history' => $history,
                    'total' => \DB::table('schedule_history')->count(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Schedule history retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Manually run a scheduled task.
     */
    public function runTask(string $command): array
    {
        try {
            // Sanitize command - only allow known commands
            $schedule = app(Schedule::class);
            $events = $schedule->events();
            
            $found = false;
            foreach ($events as $event) {
                if ($event->command === $command) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return [
                    'success' => false,
                    'error' => 'Command not found in scheduled tasks',
                ];
            }

            // Execute the command
            \Artisan::call($command);
            $output = \Artisan::output();

            return [
                'success' => true,
                'message' => "Task executed: {$command}",
                'output' => $output,
            ];

        } catch (\Exception $e) {
            Log::error('Manual task execution failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get next run time for an event.
     */
    protected function getNextRunTime($event): ?string
    {
        try {
            if (method_exists($event, 'nextRunDate')) {
                return $event->nextRunDate()->toDateTimeString();
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if cron is configured.
     */
    protected function isCronConfigured(): bool
    {
        // Check if there's a cron entry for artisan schedule:run
        // This is a simplified check
        return true; // Assume configured for now
    }

    /**
     * Check if schedule_history table exists.
     */
    protected function historyTableExists(): bool
    {
        try {
            return \Schema::hasTable('schedule_history');
        } catch (\Exception $e) {
            return false;
        }
    }
}
