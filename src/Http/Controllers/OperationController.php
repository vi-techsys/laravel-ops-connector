<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelOps\Connector\Services\OperationExecutor;

class OperationController extends Controller
{
    /**
     * Execute an operation.
     */
    public function execute(Request $request, OperationExecutor $executor): JsonResponse
    {
        // Check if operations are enabled
        if (!config('connector.enable_operations', true)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operations_disabled',
                'error_message' => 'Remote operations are disabled in connector configuration',
            ], 403);
        }

        // Validate request
        $operation = $request->input('operation');
        $parameters = $request->input('parameters', []);

        if (!$operation) {
            return response()->json([
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Operation name is required',
            ], 400);
        }

        // Check if operation is allowed
        $allowedOperations = config('connector.allowed_operations', []);
        if (!in_array($operation, $allowedOperations)) {
            return response()->json([
                'success' => false,
                'error_type' => 'operation_not_allowed',
                'error_message' => "Operation not allowed: {$operation}",
                'allowed_operations' => $allowedOperations,
            ], 403);
        }

        // Execute the operation
        try {
            $startTime = microtime(true);
            $result = $executor->execute($operation, $parameters);
            $durationMs = round((microtime(true) - $startTime) * 1000);

            // Check if the operation actually succeeded
            if ($result['success'] && $result['exit_code'] === 0) {
                return response()->json([
                    'success' => true,
                    'operation' => $operation,
                    'data' => $result,
                    'duration_ms' => $durationMs,
                    'timestamp' => now()->toIso8601String(),
                ]);
            } else {
                // Operation ran but failed (non-zero exit code)
                return response()->json([
                    'success' => false,
                    'error_type' => 'artisan_command_failed',
                    'error_message' => $result['output'] ?? 'Command failed with exit code ' . ($result['exit_code'] ?? 'unknown'),
                    'duration_ms' => $durationMs,
                    'timestamp' => now()->toIso8601String(),
                ], 500);
            }

        } catch (\Exception $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => false,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }
}
