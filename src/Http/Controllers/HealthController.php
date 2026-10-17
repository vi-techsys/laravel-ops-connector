<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    /**
     * Return health check information.
     */
    public function check(): JsonResponse
    {
        $checks = [
            'application' => $this->checkApplication(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'ok')
            ? 'healthy'
            : 'degraded';

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'application' => [
                'name' => config('app.name'),
                'url' => url('/'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'environment' => config('app.env'),
            ],
            'connector' => [
                'version' => '1.0.0',
                'capabilities_count' => count(config('connector.allowed_operations', [])),
            ],
            'checks' => $checks,
        ]);
    }

    /**
     * Check application status.
     */
    protected function checkApplication(): array
    {
        return [
            'status' => 'ok',
            'message' => 'Application is running',
        ];
    }

    /**
     * Check database connection.
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            
            return [
                'status' => 'ok',
                'message' => 'Database connection successful',
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Connection error',
            ];
        }
    }

    /**
     * Check cache functionality.
     */
    protected function checkCache(): array
    {
        try {
            $testKey = 'connector_health_check_' . time();
            $testValue = 'test_' . Str::random(10);
            
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'ok',
                    'message' => 'Cache is working',
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Cache read/write mismatch',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache check failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Cache error',
            ];
        }
    }
}
