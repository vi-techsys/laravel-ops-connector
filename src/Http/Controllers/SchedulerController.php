<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelOps\Connector\Services\SchedulerMonitor;

class SchedulerController extends Controller
{
    protected SchedulerMonitor $monitor;

    public function __construct(SchedulerMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    /**
     * Inspect scheduled tasks.
     */
    public function inspect(): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $result = $this->monitor->inspect();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'schedule_inspect_failed',
                'error_message' => $result['error'] ?? 'Failed to inspect schedule',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get scheduler execution history.
     */
    public function history(Request $request): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $limit = $request->input('limit', 50);
        $result = $this->monitor->getHistory($limit);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'schedule_history_failed',
                'error_message' => $result['error'] ?? 'Failed to fetch schedule history',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Manually run a scheduled task.
     */
    public function runTask(Request $request): JsonResponse
    {
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled',
            ], 403);
        }

        $command = $request->input('command');

        if (!$command) {
            return response()->json([
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Command parameter is required',
            ], 400);
        }

        $result = $this->monitor->runTask($command);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'task_execution_failed',
                'error_message' => $result['error'] ?? 'Failed to execute task',
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
