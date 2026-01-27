<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\QueueAdapter;

class JobsPanel
{
    public function __construct(private QueueAdapter $adapter) {}

    public function render(): string
    {
        // Mock recent jobs
        $output = "\033[36mJobs (Recent):\033[0m\n";
        $output .= "- Job ID 123: \033[32mprocessed\033[0m at " . now() . "\n";
        $output .= "- Job ID 124: \033[31mfailed\033[0m at " . now() . "\n";
        return $output;
    }
}