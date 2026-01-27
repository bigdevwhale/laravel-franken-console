<?php

declare(strict_types=1);

namespace Franken\Console\UI;

class SchedulerPanel
{
    public function render(): string
    {
        // Mock scheduler runs
        $output = "\033[37mScheduler:\033[0m\n";
        $output .= "Last run: " . now()->subMinutes(5) . "\n";
        $output .= "Next run: " . now()->addMinutes(5) . "\n";
        $output .= "Failed: 0\n";
        return $output;
    }
}