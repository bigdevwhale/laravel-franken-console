<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\MetricsAdapter;

class MetricsPanel
{
    public function __construct(private MetricsAdapter $adapter) {}

    public function render(): string
    {
        $metrics = $this->adapter->getMetrics();
        $output = "\033[31mMetrics (Sparklines):\033[0m\n";
        $output .= "Queues: " . implode('', array_map(fn($v) => chr(0x2580 + min(7, $v)), $metrics['queues'])) . "\n";
        $output .= "Requests: " . implode('', array_map(fn($v) => chr(0x2580 + min(7, $v)), $metrics['requests'])) . "\n";
        $output .= "Errors: " . implode('', array_map(fn($v) => chr(0x2580 + min(7, $v)), $metrics['errors'])) . "\n";
        return $output;
    }
}