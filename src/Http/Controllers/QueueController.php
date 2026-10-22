<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelOps\Connector\Services\QueueMonitor;

class QueueController extends Controller
{
    protected QueueMonitor $monitor;

    public function __construct(QueueMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    /**
     * Inspect queue status.
     */
    public function inspect(Request $request): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $queuesParam = $request->input('queues');
        $result = $this->monitor->inspect($queuesParam);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'queue_inspect_failed',
                'error_message' => $result['error'] ?? 'Failed to inspect queue',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get failed job details.
     */
    public function failed(Request $request): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $result = $this->monitor->getFailedDetails($limit, $offset);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'failed_jobs_fetch_failed',
                'error_message' => $result['error'] ?? 'Failed to fetch failed jobs',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $result = $this->monitor->retryAll();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'retry_failed',
                'error_message' => $result['error'] ?? 'Failed to retry jobs',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['output'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Purge queue jobs.
     */
    public function purge(Request $request): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $queue = $request->input('queue', 'default');
        $result = $this->monitor->purge($queue);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'purge_failed',
                'error_message' => $result['error'] ?? 'Failed to purge queue',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['output'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
