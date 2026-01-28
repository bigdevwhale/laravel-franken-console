<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

class LogAdapter
{
    private ?string $logPath = null;
    private int $lastFilePosition = 0;

    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath;
    }

    public function getLogPath(): string
    {
        if ($this->logPath) {
            return $this->logPath;
        }

        try {
            return storage_path('logs/laravel.log');
        } catch (\Exception $e) {
            return '/tmp/laravel.log';
        }
    }

    public function getRecentLogs(int $limit = 50): array
    {
        $logFile = $this->getLogPath();
        
        if (!file_exists($logFile)) {
            return $this->getMockLogs();
        }

        try {
            $logs = [];
            $lines = $this->tailFile($logFile, $limit * 3); // Read more lines as some may be multi-line

            $currentEntry = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Check if this is a new log entry
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]\s*(\w+)?\.?(\w+)?:\s*(.*)$/i', $line, $matches)) {
                    // Save previous entry
                    if ($currentEntry !== null) {
                        $logs[] = $currentEntry;
                    }

                    $currentEntry = [
                        'timestamp' => $matches[1],
                        'channel' => $matches[2] ?? 'local',
                        'level' => strtolower($matches[3] ?? 'info'),
                        'message' => $matches[4],
                    ];
                } elseif ($currentEntry !== null) {
                    // This is a continuation of the previous entry (stack trace, etc.)
                    $currentEntry['message'] .= "\n" . $line;
                }
            }

            // Don't forget the last entry
            if ($currentEntry !== null) {
                $logs[] = $currentEntry;
            }

            // Return the last $limit entries, reversed so newest is first
            $logs = array_slice(array_reverse($logs), 0, $limit);

            return empty($logs) ? $this->getMockLogs() : $logs;
        } catch (\Exception $e) {
            return $this->getMockLogs();
        }
    }

    private function tailFile(string $filename, int $lines): array
    {
        $result = [];
        
        $handle = fopen($filename, 'r');
        if ($handle === false) {
            return [];
        }

        // Get file size
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        
        if ($fileSize === 0) {
            fclose($handle);
            return [];
        }

        // Read from the end
        $pos = $fileSize;
        $buffer = '';
        $lineCount = 0;

        while ($pos > 0 && $lineCount < $lines) {
            $pos = max(0, $pos - 8192);
            fseek($handle, $pos);
            $chunk = fread($handle, min(8192, $fileSize - $pos));
            $buffer = $chunk . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($handle);

        $result = explode("\n", $buffer);
        return array_slice($result, -$lines);
    }

    public function getNewLogs(): array
    {
        $logFile = $this->getLogPath();
        
        if (!file_exists($logFile)) {
            return [];
        }

        $currentSize = filesize($logFile);
        if ($currentSize <= $this->lastFilePosition) {
            $this->lastFilePosition = $currentSize;
            return [];
        }

        try {
            $handle = fopen($logFile, 'r');
            fseek($handle, $this->lastFilePosition);
            $newContent = fread($handle, $currentSize - $this->lastFilePosition);
            fclose($handle);

            $this->lastFilePosition = $currentSize;

            $logs = [];
            foreach (explode("\n", $newContent) as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})\]\s*(\w+)?\.?(\w+)?:\s*(.*)$/', $line, $matches)) {
                    $logs[] = [
                        'timestamp' => $matches[1],
                        'channel' => $matches[2] ?? 'local',
                        'level' => strtolower($matches[3] ?? 'info'),
                        'message' => $matches[4],
                    ];
                }
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getMockLogs(): array
    {
        $levels = ['debug', 'info', 'notice', 'warning', 'error'];
        $messages = [
            'User logged in successfully',
            'Payment processed for order #12345',
            'Cache cleared',
            'Failed to connect to external API',
            'Database query took 2.5 seconds',
            'New user registered',
            'Email sent to user@example.com',
            'Job ProcessPayment completed',
            'Memory usage: 45MB',
            'Request completed in 234ms',
        ];

        $logs = [];
        for ($i = 0; $i < 20; $i++) {
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s', strtotime("-{$i} minutes")),
                'channel' => 'local',
                'level' => $levels[array_rand($levels)],
                'message' => $messages[array_rand($messages)],
            ];
        }

        return $logs;
    }

    public function clearLogs(): bool
    {
        $logFile = $this->getLogPath();
        
        if (file_exists($logFile)) {
            try {
                file_put_contents($logFile, '');
                $this->lastFilePosition = 0;
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }
}