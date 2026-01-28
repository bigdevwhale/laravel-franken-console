<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

class MetricsAdapter
{
    private array $memoryHistory = [];
    private array $requestsHistory = [];
    private array $responseTimeHistory = [];
    private array $queueHistory = [];
    private array $errorHistory = [];
    private float $lastCollectionTime = 0;

    public function __construct()
    {
        // Initialize with some baseline data
        $this->initializeHistory();
    }

    private function initializeHistory(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->memoryHistory[] = memory_get_usage(true) / 1024 / 1024 + rand(-5, 5);
            $this->requestsHistory[] = rand(10, 25);
            $this->responseTimeHistory[] = rand(40, 80);
            $this->queueHistory[] = rand(3, 15);
            $this->errorHistory[] = rand(0, 2);
        }
    }

    public function getMetrics(): array
    {
        $this->collectMetrics();
        
        return [
            'memory' => $this->memoryHistory,
            'requests' => $this->requestsHistory,
            'response_time' => $this->responseTimeHistory,
            'queues' => $this->queueHistory,
            'errors' => $this->errorHistory,
        ];
    }

    private function collectMetrics(): void
    {
        $now = microtime(true);
        
        // Only collect new metrics every second
        if ($now - $this->lastCollectionTime < 1) {
            return;
        }
        
        $this->lastCollectionTime = $now;

        // Memory - actual value
        $this->addToHistory($this->memoryHistory, memory_get_usage(true) / 1024 / 1024);

        // Requests - simulated (in a real app, you'd track actual requests)
        $lastRequests = end($this->requestsHistory) ?: 15;
        $this->addToHistory($this->requestsHistory, max(0, $lastRequests + rand(-3, 5)));

        // Response time - simulated
        $lastResponseTime = end($this->responseTimeHistory) ?: 50;
        $this->addToHistory($this->responseTimeHistory, max(10, $lastResponseTime + rand(-10, 15)));

        // Queue jobs - try to get real count
        $queueJobs = $this->getQueueJobCount();
        $this->addToHistory($this->queueHistory, $queueJobs);

        // Errors - simulated with occasional spikes
        $errorChance = rand(1, 100);
        $errors = $errorChance <= 5 ? rand(1, 3) : 0;
        $this->addToHistory($this->errorHistory, $errors);
    }

    private function addToHistory(array &$history, float $value): void
    {
        $history[] = $value;
        
        // Keep only last 10 values
        if (count($history) > 10) {
            array_shift($history);
        }
    }

    private function getQueueJobCount(): int
    {
        try {
            return \DB::table('jobs')->count();
        } catch (\Exception $e) {
            $last = end($this->queueHistory) ?: 5;
            return max(0, $last + rand(-2, 3));
        }
    }

    public function getAverages(): array
    {
        return [
            'memory' => $this->average($this->memoryHistory),
            'requests' => $this->average($this->requestsHistory),
            'response_time' => $this->average($this->responseTimeHistory),
            'queues' => $this->average($this->queueHistory),
            'errors' => $this->average($this->errorHistory),
        ];
    }

    private function average(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        return array_sum($values) / count($values);
    }

    public function getMaximums(): array
    {
        return [
            'memory' => max($this->memoryHistory) ?: 0,
            'requests' => max($this->requestsHistory) ?: 0,
            'response_time' => max($this->responseTimeHistory) ?: 0,
            'queues' => max($this->queueHistory) ?: 0,
            'errors' => max($this->errorHistory) ?: 0,
        ];
    }

    public function getCurrentValues(): array
    {
        return [
            'memory' => end($this->memoryHistory) ?: 0,
            'requests' => end($this->requestsHistory) ?: 0,
            'response_time' => end($this->responseTimeHistory) ?: 0,
            'queues' => end($this->queueHistory) ?: 0,
            'errors' => end($this->errorHistory) ?: 0,
        ];
    }
}