<?php

namespace LaravelOps\Connector\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class QueueMonitor
{
    /**
     * Inspect queue status and pending jobs.
     */
    public function inspect(): array
    {
        try {
            $driver = config('queue.default', 'sync');
            $queues = [];

            // Get queue sizes for common queues
            $queueNames = ['default', 'high', 'low', 'emails', 'notifications'];
            
            foreach ($queueNames as $queueName) {
                $size = $this->getQueueSize($queueName, $driver);
                if ($size !== null) {
                    $queues[$queueName] = [
                        'size' => $size,
                        'driver' => $driver,
                    ];
                }
            }

            // Get failed job count
            $failedCount = $this->getFailedJobCount();

            return [
                'success' => true,
                'data' => [
                    'driver' => $driver,
                    'queues' => $queues,
                    'failed_count' => $failedCount,
                    'worker_status' => $this->getWorkerStatus(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Queue inspection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get detailed information about failed jobs.
     */
    public function getFailedDetails(int $limit = 50, int $offset = 0): array
    {
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $sanitizedJobs = $failedJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid ?? null,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                    'exception_summary' => $this->sanitizeException($job->exception),
                ];
            });

            return [
                'success' => true,
                'data' => [
                    'jobs' => $sanitizedJobs,
                    'total' => DB::table('failed_jobs')->count(),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed job details retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();
            
            if ($count === 0) {
                return [
                    'success' => true,
                    'message' => 'No failed jobs to retry',
                ];
            }

            // Use Artisan to retry all failed jobs
            \Artisan::call('queue:retry', ['id' => 'all']);
            $output = \Artisan::output();

            return [
                'success' => true,
                'message' => "Retrying {$count} failed jobs",
                'output' => $output,
            ];

        } catch (\Exception $e) {
            Log::error('Retry all failed jobs failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Purge all jobs from queue.
     */
    public function purge(string $queue = 'default'): array
    {
        try {
            // Use Artisan to flush failed jobs
            \Artisan::call('queue:flush');
            $output = \Artisan::output();

            return [
                'success' => true,
                'message' => "Purged jobs from queue: {$queue}",
                'output' => $output,
            ];

        } catch (\Exception $e) {
            Log::error('Purge queue failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue size based on driver.
     */
    protected function getQueueSize(string $queue, string $driver): ?int
    {
        try {
            if ($driver === 'database') {
                return DB::table('jobs')
                    ->where('queue', $queue)
                    ->count();
            }

            // For other drivers (redis, sqs, etc.), return null
            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get failed job count.
     */
    protected function getFailedJobCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get worker status (informational).
     */
    protected function getWorkerStatus(): string
    {
        $driver = config('queue.default', 'sync');
        
        if ($driver === 'sync') {
            return 'Sync driver - jobs execute immediately';
        }

        return "Driver: {$driver} - requires worker process (php artisan queue:work)";
    }

    /**
     * Sanitize exception message to prevent information leakage.
     */
    protected function sanitizeException(string $exception): string
    {
        // Remove stack trace, keep only the main error message
        $lines = explode("\n", $exception);
        $summary = $lines[0] ?? 'Unknown error';
        
        // Limit length
        return substr($summary, 0, 500);
    }
}
