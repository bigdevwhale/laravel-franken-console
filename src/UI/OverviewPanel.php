<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Terminal;
use Franken\Console\Support\Theme;

class OverviewPanel
{
    private Theme $theme;
    private Terminal $terminal;
    private float $startTime;

    public function __construct(?Terminal $terminal = null)
    {
        $this->theme = new Theme();
        $this->terminal = $terminal ?? new Terminal();
        $this->startTime = microtime(true);
    }

    public function render(): string
    {
        $output = $this->theme->styled("  ╔══════════════════════════════════════════════════════════╗\n", 'primary');
        $output .= $this->theme->styled("  ║", 'primary') . "           " . $this->theme->bold("Franken-Console Dashboard") . "                    " . $this->theme->styled("║\n", 'primary');
        $output .= $this->theme->styled("  ╚══════════════════════════════════════════════════════════╝\n\n", 'primary');

        // System info
        $output .= $this->theme->styled("  System Information\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────\n", 'muted');

        // PHP Info
        $output .= "  PHP Version:     " . $this->theme->styled(PHP_VERSION, 'info') . "\n";
        $output .= "  OS:              " . $this->theme->styled($this->getOsInfo(), 'info') . "\n";

        // Memory usage
        $memoryUsage = $this->getMemoryUsage();
        $output .= "  Memory Usage:    " . $this->theme->styled($memoryUsage['used'], 'info');
        $output .= " / " . $this->theme->dim($memoryUsage['limit']) . "\n";

        // Uptime
        $uptime = $this->formatUptime(microtime(true) - $this->startTime);
        $output .= "  Session Uptime:  " . $this->theme->styled($uptime, 'info') . "\n";

        $output .= "\n";

        // Laravel Info
        $output .= $this->theme->styled("  Laravel Application\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────\n", 'muted');

        try {
            $version = app()->version();
            $environment = app()->environment();
            $debugMode = config('app.debug') ? $this->theme->styled('ON', 'warning') : $this->theme->styled('OFF', 'success');
        } catch (\Exception $e) {
            $version = 'Unknown';
            $environment = 'Unknown';
            $debugMode = 'Unknown';
        }

        $output .= "  Laravel Version: " . $this->theme->styled($version, 'info') . "\n";
        $output .= "  Environment:     " . $this->theme->styled($environment, 'info') . "\n";
        $output .= "  Debug Mode:      " . $debugMode . "\n";

        // Database connection
        $dbStatus = $this->checkDatabaseConnection();
        $dbStatusColor = $dbStatus === 'Connected' ? 'success' : 'error';
        $output .= "  Database:        " . $this->theme->styled($dbStatus, $dbStatusColor) . "\n";

        // Cache driver
        try {
            $cacheDriver = config('cache.default', 'file');
        } catch (\Exception $e) {
            $cacheDriver = 'Unknown';
        }
        $output .= "  Cache Driver:    " . $this->theme->styled($cacheDriver, 'info') . "\n";

        // Queue driver
        try {
            $queueDriver = config('queue.default', 'sync');
        } catch (\Exception $e) {
            $queueDriver = 'Unknown';
        }
        $output .= "  Queue Driver:    " . $this->theme->styled($queueDriver, 'info') . "\n";

        $output .= "\n";

        // Quick stats box
        $output .= $this->theme->styled("  Quick Stats\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────\n", 'muted');

        $output .= "  " . $this->renderMiniSparkline("CPU Load", $this->getCpuLoadHistory()) . "\n";
        $output .= "  " . $this->renderMiniSparkline("Memory", $this->getMemoryHistory()) . "\n";

        return $output;
    }

    private function getOsInfo(): string
    {
        if ($this->terminal->isWindows()) {
            return 'Windows ' . php_uname('r');
        }
        return php_uname('s') . ' ' . php_uname('r');
    }

    private function getMemoryUsage(): array
    {
        $used = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = ini_get('memory_limit');

        return [
            'used' => $this->formatBytes($used),
            'peak' => $this->formatBytes($peak),
            'limit' => $limit,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    private function formatUptime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }
        return sprintf('%ds', $secs);
    }

    private function checkDatabaseConnection(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'Connected';
        } catch (\Exception $e) {
            return 'Disconnected';
        }
    }

    private function getCpuLoadHistory(): array
    {
        // Return mock data for sparkline (0-100 values)
        return [30, 45, 35, 50, 40, 55, 45, 60, 50, 45];
    }

    private function getMemoryHistory(): array
    {
        // Return mock data based on current usage
        $current = (memory_get_usage(true) / (1024 * 1024)) / 128 * 100; // Assume 128MB limit for display
        $current = min(100, max(0, $current));
        
        return array_map(fn() => $current + rand(-10, 10), range(1, 10));
    }

    private function renderMiniSparkline(string $label, array $values): string
    {
        $sparkChars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
        $max = max($values) ?: 1;
        $min = min($values);
        $range = $max - $min ?: 1;

        $spark = '';
        foreach ($values as $value) {
            $normalized = ($value - $min) / $range;
            $index = (int) floor($normalized * (count($sparkChars) - 1));
            $spark .= $sparkChars[$index];
        }

        $current = end($values);
        $color = $current > 80 ? 'error' : ($current > 60 ? 'warning' : 'success');

        return sprintf("%-12s %s %s", $label . ':', $this->theme->styled($spark, $color), $this->theme->dim(round($current) . '%'));
    }
}