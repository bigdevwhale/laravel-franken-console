<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\CacheAdapter;

class CacheConfigPanel
{
    public function __construct(private CacheAdapter $adapter) {}

    public function render(): string
    {
        $stats = $this->adapter->getCacheStats();
        $output = "\033[35mCache/Config:\033[0m\n";
        $output .= "Driver: {$stats['driver']}\n";
        $output .= "Size: {$stats['size']}\n";
        $output .= "Available commands: cache:clear, config:clear, view:clear, route:clear\n";
        return $output;
    }
}