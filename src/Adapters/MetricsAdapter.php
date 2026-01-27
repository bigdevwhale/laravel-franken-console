<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

class MetricsAdapter
{
    public function getMetrics(): array
    {
        // Mock sparklines data
        return [
            'queues' => [1, 2, 3, 4, 5],
            'requests' => [10, 12, 8, 15, 9],
            'errors' => [0, 1, 0, 0, 1],
        ];
    }
}