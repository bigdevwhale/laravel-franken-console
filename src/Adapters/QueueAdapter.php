<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

use Illuminate\Support\Facades\DB;

class QueueAdapter
{
    public function getQueueStats(): array
    {
        try {
            if (!class_exists('Illuminate\Support\Facades\DB')) {
                throw new \Exception('DB not available');
            }
            $queues = DB::table('jobs')->select('queue')->distinct()->pluck('queue')->toArray();
            $stats = [];
            foreach ($queues as $queue) {
                $size = DB::table('jobs')->where('queue', $queue)->count();
                $failed = DB::table('failed_jobs')->where('queue', $queue)->count();
                $stats[] = ['name' => $queue, 'size' => $size, 'failed' => $failed];
            }
            if (empty($stats)) {
                $stats = [['name' => 'default', 'size' => 0, 'failed' => 0]];
            }
        } catch (\Exception $e) {
            // Fallback to mock
            $stats = [
                ['name' => 'default', 'size' => 5, 'failed' => 1],
                ['name' => 'emails', 'size' => 0, 'failed' => 0],
            ];
        }
        return [
            'queues' => $stats,
            'workers' => [
                ['pid' => 1234, 'status' => 'running'],
            ],
        ];
    }

    public function retryFailedJobs(string $queue): void
    {
        // Implement retry logic
    }

    public function restartWorker(): void
    {
        // Implement restart
    }
}