<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\MetricsAdapter;
use Franken\Console\Support\Theme;

class MetricsPanel
{
    private Theme $theme;

    public function __construct(private MetricsAdapter $adapter)
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $metrics = $this->adapter->getMetrics();
        
        $output = "\n";
        $output .= $this->theme->styled("  Application Metrics\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        // Real-time metrics
        $output .= "\n";
        $output .= $this->theme->bold("  Current Performance\n");
        $output .= "\n";

        // Memory usage sparkline
        $output .= "  " . $this->renderMetricRow(
            'Memory Usage',
            $metrics['memory'] ?? $this->getMemoryMetrics(),
            'MB',
            'info'
        ) . "\n";

        // CPU-like metric (requests per second simulation)
        $output .= "  " . $this->renderMetricRow(
            'Requests/sec',
            $metrics['requests'] ?? [10, 15, 12, 18, 14, 20, 16, 22, 18, 15],
            'req/s',
            'success'
        ) . "\n";

        // Response time
        $output .= "  " . $this->renderMetricRow(
            'Response Time',
            $metrics['response_time'] ?? [45, 52, 48, 55, 50, 60, 55, 65, 58, 52],
            'ms',
            'warning'
        ) . "\n";

        // Queue processing
        $output .= "  " . $this->renderMetricRow(
            'Jobs Processed',
            $metrics['queues'] ?? [5, 8, 6, 10, 7, 12, 8, 15, 10, 8],
            'jobs/min',
            'primary'
        ) . "\n";

        // Errors
        $output .= "  " . $this->renderMetricRow(
            'Error Rate',
            $metrics['errors'] ?? [0, 1, 0, 0, 2, 0, 1, 0, 0, 0],
            '%',
            'error'
        ) . "\n";

        $output .= "\n";
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');
        $output .= $this->theme->bold("  Summary Statistics\n");
        $output .= "\n";

        // Summary stats
        $stats = $this->calculateStats($metrics);
        
        $output .= sprintf(
            "  %-20s %s\n",
            'Peak Memory:',
            $this->theme->styled($stats['peak_memory'] . ' MB', 'info')
        );
        $output .= sprintf(
            "  %-20s %s\n",
            'Avg Response Time:',
            $this->theme->styled($stats['avg_response'] . ' ms', 'info')
        );
        $output .= sprintf(
            "  %-20s %s\n",
            'Total Requests:',
            $this->theme->styled((string)$stats['total_requests'], 'info')
        );
        $output .= sprintf(
            "  %-20s %s\n",
            'Error Count:',
            $this->theme->styled((string)$stats['error_count'], $stats['error_count'] > 0 ? 'error' : 'success')
        );
        $output .= sprintf(
            "  %-20s %s\n",
            'Jobs Processed:',
            $this->theme->styled((string)$stats['jobs_processed'], 'info')
        );

        $output .= "\n";
        $output .= $this->theme->dim("  Data refreshes every " . config('franken.polling_interval', 2) . " seconds\n");

        return $output;
    }

    private function renderMetricRow(string $label, array $values, string $unit, string $color): string
    {
        $sparkline = $this->renderSparkline($values, $color);
        $current = end($values);
        $trend = $this->getTrend($values);
        
        $trendIcon = match($trend) {
            'up' => $this->theme->styled('↑', 'error'),
            'down' => $this->theme->styled('↓', 'success'),
            default => $this->theme->dim('→'),
        };

        return sprintf(
            "%-18s %s %s %s %s",
            $label . ':',
            $sparkline,
            $this->theme->styled(sprintf('%6.1f', $current), $color),
            $this->theme->dim($unit),
            $trendIcon
        );
    }

    private function renderSparkline(array $values, string $color): string
    {
        $sparkChars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
        
        if (empty($values)) {
            return str_repeat('▁', 10);
        }

        $max = max($values) ?: 1;
        $min = min($values);
        $range = $max - $min ?: 1;

        $spark = '';
        foreach ($values as $value) {
            $normalized = ($value - $min) / $range;
            $index = (int) floor($normalized * (count($sparkChars) - 1));
            $spark .= $sparkChars[$index];
        }

        return $this->theme->styled($spark, $color);
    }

    private function getTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'stable';
        }

        $recent = array_slice($values, -3);
        $older = array_slice($values, -6, 3);

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = !empty($older) ? array_sum($older) / count($older) : $recentAvg;

        $diff = $recentAvg - $olderAvg;
        $threshold = $olderAvg * 0.1; // 10% change threshold

        if ($diff > $threshold) {
            return 'up';
        } elseif ($diff < -$threshold) {
            return 'down';
        }
        
        return 'stable';
    }

    private function getMemoryMetrics(): array
    {
        $current = memory_get_usage(true) / 1024 / 1024;
        $values = [];
        for ($i = 0; $i < 10; $i++) {
            $values[] = $current + rand(-5, 5);
        }
        return $values;
    }

    private function calculateStats(array $metrics): array
    {
        return [
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            'avg_response' => round(array_sum($metrics['response_time'] ?? [50]) / count($metrics['response_time'] ?? [1]), 1),
            'total_requests' => array_sum($metrics['requests'] ?? [100]),
            'error_count' => array_sum($metrics['errors'] ?? [0]),
            'jobs_processed' => array_sum($metrics['queues'] ?? [50]),
        ];
    }
}