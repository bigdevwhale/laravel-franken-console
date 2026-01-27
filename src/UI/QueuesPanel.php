<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\QueueAdapter;

class QueuesPanel
{
    public function __construct(private QueueAdapter $adapter) {}

    public function render(): string
    {
        $stats = $this->adapter->getQueueStats();
        $output = "\033[34mQueues:\033[0m\n";
        foreach ($stats['queues'] as $queue) {
            $output .= "- {$queue['name']}: {$queue['size']} jobs, \033[31m{$queue['failed']} failed\033[0m\n";
        }
        return $output;
    }
}