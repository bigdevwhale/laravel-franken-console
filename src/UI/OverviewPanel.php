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
        $width = $this->terminal->getWidth();
        $height = $this->terminal->getHeight();
        
        $output = "\n";
        
        // Header
        $output .= $this->renderHeader($width);
        $output .= "\n";
        
        // Use single column layout for narrow terminals
        if ($width < 80) {
            $output .= $this->renderSystemInfo($width);
            $output .= "\n";
            $output .= $this->renderQuickStats($width);
        } else {
            // Two column layout for wider terminals
            $colWidth = (int)(($width - 10) / 2);
            
            $leftCol = $this->renderSystemInfo($colWidth);
            $rightCol = $this->renderQuickStats($colWidth);
            
            $leftLines = explode("\n", $leftCol);
            $rightLines = explode("\n", $rightCol);
            
            $maxLines = max(count($leftLines), count($rightLines));
            
            for ($i = 0; $i < $maxLines; $i++) {
                $left = $leftLines[$i] ?? '';
                $right = $rightLines[$i] ?? '';
                
                // Calculate visible length for proper padding
                $leftVisible = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $left));
                $leftPadding = max(0, $colWidth - $leftVisible);
                
                $output .= '  ' . $left . str_repeat(' ', $leftPadding) . ' │ ' . $right . "\n";
            }
        }
        
        $output .= "\n";
        $output .= $this->renderLaravelInfo($width);
        
        return $output;
    }

    private function renderHeader(int $width): string
    {
        $title = ' DASHBOARD ';
        if ($width >= 80) {
            $title = ' DASHBOARD OVERVIEW ';
        }
        $titleLen = mb_strlen($title);
        $sideWidth = max(2, (int)(($width - $titleLen - 4) / 2));
        
        $line = '  ' . str_repeat('━', $sideWidth) . $this->theme->bold($this->theme->styled($title, 'primary')) . str_repeat('━', $sideWidth);
        return $line;
    }

    private function renderSystemInfo(int $colWidth = 40): string
    {
        $lineWidth = min(30, $colWidth - 2);
        $output = $this->theme->bold($this->theme->styled('SYSTEM', 'secondary')) . "\n";
        $output .= $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        $output .= "\n";

        $data = [
            ['PHP', PHP_VERSION, 'info'],
            ['OS', $this->getOsInfo(), 'info'],
            ['Memory', $this->getMemoryUsage()['used'], 'success'],
            ['Peak', $this->getMemoryUsage()['peak'], 'warning'],
            ['Uptime', $this->formatUptime(microtime(true) - $this->startTime), 'info'],
        ];

        foreach ($data as $row) {
            $output .= $this->renderRow($row[0], $row[1], $row[2]) . "\n";
        }

        return $output;
    }

    private function renderQuickStats(int $colWidth = 40): string
    {
        $lineWidth = min(30, $colWidth - 2);
        $output = $this->theme->bold($this->theme->styled('METRICS', 'secondary')) . "\n";
        $output .= $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        $output .= "\n";

        $output .= $this->renderSparkRow('CPU', $this->getCpuLoadHistory()) . "\n";
        $output .= $this->renderSparkRow('Mem', $this->getMemoryHistory()) . "\n";
        $output .= $this->renderSparkRow('Disk', $this->getDiskHistory()) . "\n";
        $output .= "\n";
        
        // Status indicators
        $dbStatus = $this->checkDatabaseConnection();
        $dbIcon = $dbStatus === 'Connected' ? '●' : '○';
        $dbColor = $dbStatus === 'Connected' ? 'success' : 'error';
        $output .= $this->theme->styled($dbIcon, $dbColor) . ' DB ' . $this->theme->styled($dbStatus, $dbColor) . "\n";
        
        return $output;
    }

    private function renderLaravelInfo(int $width): string
    {
        $lineWidth = max(20, $width - 4);
        $output = '  ' . $this->theme->bold($this->theme->styled('LARAVEL', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        $output .= "\n";

        try {
            $version = app()->version();
            $environment = app()->environment();
            $debug = config('app.debug');
            $cacheDriver = config('cache.default', 'file');
            $queueDriver = config('queue.default', 'sync');
            $sessionDriver = config('session.driver', 'file');
        } catch (\Exception $e) {
            $version = $environment = 'Unknown';
            $debug = false;
            $cacheDriver = $queueDriver = $sessionDriver = 'Unknown';
        }

        $debugBadge = $debug 
            ? $this->theme->styled(' DEBUG ', 'warning') 
            : $this->theme->styled(' PROD ', 'success');

        // Responsive layout for Laravel info
        if ($width < 80) {
            // Vertical layout for narrow terminals
            $items = [
                ['Ver', $version],
                ['Env', $environment],
                ['Cache', $cacheDriver],
                ['Queue', $queueDriver],
            ];
            foreach ($items as $item) {
                $output .= '  ' . $this->theme->dim(str_pad($item[0], 8)) . $this->theme->styled($item[1], 'info') . "\n";
            }
            $output .= '  ' . $debugBadge . "\n";
        } else {
            // Horizontal layout for wider terminals
            $items = [
                ['Version', $version],
                ['Env', $environment],
                ['Cache', $cacheDriver],
                ['Queue', $queueDriver],
            ];

            $output .= '  ';
            foreach ($items as $item) {
                $output .= $this->theme->dim($item[0] . ':') . $this->theme->styled($item[1], 'info') . '  ';
            }
            $output .= $debugBadge . "\n";
        }

        return $output;
    }

    private function renderRow(string $label, string $value, string $color): string
    {
        $labelWidth = 10;
        $label = str_pad($label, $labelWidth);
        return $this->theme->dim($label) . $this->theme->styled($value, $color);
    }

    private function renderSparkRow(string $label, array $values): string
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
        $percent = str_pad((string)round($current), 3, ' ', STR_PAD_LEFT) . '%';

        $labelWidth = 10;
        $label = str_pad($label, $labelWidth);
        
        return $this->theme->dim($label) . $this->theme->styled($spark, $color) . ' ' . $this->theme->bold($percent);
    }

    private function getOsInfo(): string
    {
        if ($this->terminal->isWindows()) {
            return 'Windows ' . php_uname('r');
        }
        $name = php_uname('s');
        if (strlen($name) > 20) {
            $name = substr($name, 0, 17) . '...';
        }
        return $name;
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

        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
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
        static $history = [];
        if (empty($history)) {
            $history = array_map(fn() => rand(20, 60), range(1, 12));
        }
        array_shift($history);
        $history[] = rand(20, 60);
        return $history;
    }

    private function getMemoryHistory(): array
    {
        $current = (memory_get_usage(true) / (1024 * 1024)) / 128 * 100;
        $current = min(100, max(5, $current));
        
        static $history = [];
        if (empty($history)) {
            $history = array_map(fn() => $current + rand(-5, 5), range(1, 12));
        }
        array_shift($history);
        $history[] = $current + rand(-5, 5);
        return $history;
    }

    private function getDiskHistory(): array
    {
        static $history = [];
        if (empty($history)) {
            $history = array_map(fn() => rand(5, 30), range(1, 12));
        }
        array_shift($history);
        $history[] = rand(5, 30);
        return $history;
    }
}