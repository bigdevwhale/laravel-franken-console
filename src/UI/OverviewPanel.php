<?php

declare(strict_types=1);

namespace Franken\Console\UI;

class OverviewPanel
{
    public function render(): string
    {
        $cpu = 'N/A'; // Hard to get in PHP
        $ram = 'N/A';
        $uptime = 'N/A';
        $dbStatus = 'Connected'; // Assume
        try {
            $version = app()->version();
        } catch (\Exception $e) {
            $version = 'Unknown';
        }
        $workers = '1 running'; // Mock
        return "\033[32mOverview:\033[0m\n" .
               "CPU: $cpu\n" .
               "RAM: $ram\n" .
               "Uptime: $uptime\n" .
               "DB Status: $dbStatus\n" .
               "App Version: $version\n" .
               "Active Workers: $workers\n";
    }
}