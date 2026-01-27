<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

use Illuminate\Support\Facades\Log;

class LogAdapter
{
    public function getRecentLogs(int $limit = 50): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile), -$limit);
                $logs = [];
                foreach ($lines as $line) {
                    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/', $line, $matches)) {
                        $logs[] = [
                            'level' => $matches[3],
                            'message' => $matches[4],
                            'timestamp' => $matches[1],
                        ];
                    }
                }
                return array_slice($logs, -$limit);
            }
        } catch (\Exception $e) {
            // Fallback
        }
        return [
            ['level' => 'error', 'message' => 'Sample error', 'timestamp' => now()],
        ];
    }

    public function tailLogs(): iterable
    {
        // Yield log lines
        yield ['level' => 'info', 'message' => 'Log line', 'timestamp' => now()];
    }
}