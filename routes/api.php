<?php

use Illuminate\Support\Facades\Route;
use LaravelOps\Connector\Http\Controllers\EnrollmentController;
use LaravelOps\Connector\Http\Controllers\HealthController;
use LaravelOps\Connector\Http\Controllers\CapabilityController;
use LaravelOps\Connector\Http\Controllers\OperationController;
use LaravelOps\Connector\Http\Controllers\LogController;
use LaravelOps\Connector\Http\Controllers\QueueController;
use LaravelOps\Connector\Http\Controllers\SchedulerController;

/*
|--------------------------------------------------------------------------
| Connector API Routes
|--------------------------------------------------------------------------
|
| These routes are the API endpoints that the central Laravel Ops application
| will call to manage and monitor this connected application.
|
| All routes are prefixed with /api/connector/v1
|
*/

Route::prefix('api/connector/v1')->group(function () {

    // Enrollment endpoint (validates enrollment token, not signature)
    Route::post('/enroll', [EnrollmentController::class, 'enroll']);

    // Protected endpoints (require valid signature)
    Route::middleware('connector.auth')->group(function () {

        // Health check
        Route::get('/health', [HealthController::class, 'check']);

        // Capability discovery
        Route::get('/capabilities', [CapabilityController::class, 'index']);

        // Operation execution
        Route::post('/operations/execute', [OperationController::class, 'execute']);

        // Log operations
        Route::get('/logs', [LogController::class, 'index']);
        Route::get('/logs/statistics', [LogController::class, 'statistics']);
        Route::post('/logs/clear', [LogController::class, 'clear']);

        // Queue monitoring operations
        Route::get('/queue/inspect', [QueueController::class, 'inspect']);
        Route::get('/queue/failed', [QueueController::class, 'failed']);
        Route::post('/queue/retry-all', [QueueController::class, 'retryAll']);
        Route::post('/queue/purge', [QueueController::class, 'purge']);

        // Scheduler monitoring operations
        Route::get('/schedule/inspect', [SchedulerController::class, 'inspect']);
        Route::get('/schedule/history', [SchedulerController::class, 'history']);
        Route::post('/schedule/run-task', [SchedulerController::class, 'runTask']);

    });
});
