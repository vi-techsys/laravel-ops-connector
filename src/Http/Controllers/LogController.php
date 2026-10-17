<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelOps\Connector\Services\LogReader;

class LogController extends Controller
{
    /**
     * Get recent log entries.
     */
    public function index(Request $request, LogReader $logReader): JsonResponse
    {
        // Check if operations are enabled
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled in connector configuration',
            ], 403);
        }

        // Get query parameters
        $limit = $request->input('limit', 100);
        $level = $request->input('level');
        $search = $request->input('search');
        $page = $request->input('page', 1);

        // Validate limit
        if ($limit < 1 || $limit > 1000) {
            $limit = 100;
        }

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        // Get logs
        $result = $logReader->getRecentLogs($limit + $offset, $level, $search);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'log_read_error',
                'error_message' => $result['error'] ?? 'Failed to read logs',
            ], 500);
        }

        // Apply pagination
        $paginatedLogs = array_slice($result['logs'], $offset, $limit);
        $hasMore = count($result['logs']) > ($offset + $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $paginatedLogs,
                'pagination' => [
                    'total' => $result['total'],
                    'limit' => $limit,
                    'page' => $page,
                    'has_more' => $hasMore,
                ],
                'filters' => [
                    'level' => $level,
                    'search' => $search,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get log statistics.
     */
    public function statistics(LogReader $logReader): JsonResponse
    {
        // Check if operations are enabled
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled in connector configuration',
            ], 403);
        }

        $result = $logReader->getLogStatistics();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'log_read_error',
                'error_message' => $result['error'] ?? 'Failed to read log statistics',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['statistics'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Clear log files.
     */
    public function clear(LogReader $logReader): JsonResponse
    {
        // Check if operations are enabled
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled in connector configuration',
            ], 403);
        }

        $result = $logReader->clearLogs();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error_type' => 'log_clear_error',
                'error_message' => $result['error'] ?? 'Failed to clear logs',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
