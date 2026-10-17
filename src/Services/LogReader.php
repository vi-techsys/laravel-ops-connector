<?php

namespace LaravelOps\Connector\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogReader
{
    /**
     * Get recent log entries.
     */
    public function getRecentLogs(int $limit = 100, string $level = null, string $search = null): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return [
                'success' => true,
                'logs' => [],
                'total' => 0,
                'message' => 'No log file found',
            ];
        }

        try {
            $content = File::get($logFile);
            $lines = explode("\n", $content);
            $logs = [];
            $currentLog = null;

            // Parse log entries
            foreach ($lines as $line) {
                // Check if this is a new log entry (starts with timestamp)
                if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $line)) {
                    // Save previous log entry
                    if ($currentLog !== null) {
                        $logs[] = $currentLog;
                    }
                    
                    // Start new log entry
                    $currentLog = $this->parseLogLine($line);
                } elseif ($currentLog !== null) {
                    // Append to current log entry
                    $currentLog['message'] .= "\n" . $line;
                }
            }

            // Add last log entry
            if ($currentLog !== null) {
                $logs[] = $currentLog;
            }

            // Apply filters
            if ($level) {
                $logs = array_filter($logs, fn($log) => $log['level'] === strtoupper($level));
            }

            if ($search) {
                $logs = array_filter($logs, function($log) use ($search) {
                    return stripos($log['message'], $search) !== false;
                });
            }

            // Apply limit
            $total = count($logs);
            $logs = array_slice($logs, 0, $limit);

            return [
                'success' => true,
                'logs' => array_values($logs),
                'total' => $total,
                'limit' => $limit,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'logs' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse a single log line.
     */
    protected function parseLogLine(string $line): array
    {
        // Extract timestamp
        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $timestampMatch);
        $timestamp = $timestampMatch[1] ?? null;

        // Extract environment
        preg_match('/\[(\w+)\]/', $line, $envMatch);
        $environment = $envMatch[1] ?? null;

        // Extract log level
        preg_match('/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $levelMatch);
        $level = $levelMatch[1] ?? 'INFO';

        // Extract message
        $message = preg_replace('/^\[[\d\-\s:]+\]\s+\[\w+\]\s+\.\w+:\s*/', '', $line);

        return [
            'timestamp' => $timestamp,
            'environment' => $environment,
            'level' => $level,
            'message' => $message,
        ];
    }

    /**
     * Get log statistics.
     */
    public function getLogStatistics(): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return [
                'success' => true,
                'statistics' => [
                    'total_entries' => 0,
                    'by_level' => [],
                    'file_size' => 0,
                ],
            ];
        }

        try {
            $content = File::get($logFile);
            $lines = explode("\n", $content);
            
            $stats = [
                'total_entries' => 0,
                'by_level' => [
                    'DEBUG' => 0,
                    'INFO' => 0,
                    'NOTICE' => 0,
                    'WARNING' => 0,
                    'ERROR' => 0,
                    'CRITICAL' => 0,
                    'ALERT' => 0,
                    'EMERGENCY' => 0,
                ],
                'file_size' => File::size($logFile),
            ];

            foreach ($lines as $line) {
                if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $line)) {
                    $stats['total_entries']++;
                    
                    // Count by level
                    if (preg_match('/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $matches)) {
                        $level = $matches[1];
                        if (isset($stats['by_level'][$level])) {
                            $stats['by_level'][$level]++;
                        }
                    }
                }
            }

            return [
                'success' => true,
                'statistics' => $stats,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'statistics' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear log files.
     */
    public function clearLogs(): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return [
                'success' => true,
                'message' => 'No log file to clear',
            ];
        }

        try {
            File::put($logFile, '');
            
            return [
                'success' => true,
                'message' => 'Log file cleared successfully',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
