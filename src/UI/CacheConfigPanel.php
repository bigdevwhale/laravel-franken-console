<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Support\Theme;

class CacheConfigPanel extends Panel
{
    private Theme $theme;
    private int $selectedAction = 0;
    private int $scrollOffset = 0;
    private CacheAdapter $adapter;
    private array $actions = [
        ['key' => 'cache:clear', 'name' => 'Clear Application Cache', 'desc' => 'Flush the application cache'],
        ['key' => 'config:clear', 'name' => 'Clear Config Cache', 'desc' => 'Remove the configuration cache file'],
        ['key' => 'view:clear', 'name' => 'Clear View Cache', 'desc' => 'Clear all compiled view files'],
        ['key' => 'route:clear', 'name' => 'Clear Route Cache', 'desc' => 'Remove the route cache file'],
        ['key' => 'optimize:clear', 'name' => 'Clear All Caches', 'desc' => 'Remove all cached data'],
    ];

    public function __construct(string $name = 'Cache', CacheAdapter $adapter)
    {
        parent::__construct($name);
        $this->adapter = $adapter;
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $width = $this->width;
        $height = $this->height;
        $lineWidth = max(40, $width - 4);
        
        $stats = $this->adapter->getCacheStats();
        
        $output = $this->theme->bold($this->theme->styled('CACHE CONFIGURATION', 'secondary')) . "\n";
        $output .= $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        // Current cache info (compact if limited height)
        if ($width >= 80) {
            $output .= sprintf("  %-14s %s   %-10s %s   %-10s %s\n",
                'Driver:',
                $this->theme->styled($stats['driver'], 'info'),
                'Size:',
                $this->theme->styled($stats['size'], 'info'),
                'Status:',
                $this->theme->styled($stats['status'] ?? 'active', 'success')
            );
        } else {
            $output .= sprintf("  %-12s %s | %-8s %s\n",
                'Driver:',
                $this->theme->styled($stats['driver'], 'info'),
                'Size:',
                $this->theme->styled($stats['size'], 'info')
            );
        }

        // Driver settings (only if there's room)
        if ($height >= 20) {
            try {
                $cacheConfig = config("cache.stores.{$stats['driver']}", []);
                if (!empty($cacheConfig)) {
                    $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
                    $output .= '  ' . $this->theme->bold('Driver Settings') . "\n";
                    $count = 0;
                    foreach ($cacheConfig as $key => $value) {
                        if ((is_string($value) || is_numeric($value)) && $count < 3) {
                            $output .= sprintf("  %-14s %s\n", ucfirst($key) . ':', $this->theme->dim((string)$value));
                            $count++;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        $output .= '  ' . $this->theme->bold('Available Actions') . "\n";

        foreach ($this->actions as $i => $action) {
            $marker = ($i === $this->selectedAction) ? $this->theme->styled('▸', 'primary') : ' ';
            $name = ($i === $this->selectedAction) ? 
                $this->theme->styled($action['name'], 'primary') : 
                $action['name'];
            
            if ($width >= 80) {
                $output .= sprintf(" %s %-30s %s\n", $marker, $name, $this->theme->dim($action['desc']));
            } else {
                $shortName = strlen($action['name']) > 22 ? substr($action['name'], 0, 19) . '...' : $action['name'];
                $name = ($i === $this->selectedAction) ? 
                    $this->theme->styled($shortName, 'primary') : 
                    $shortName;
                $output .= sprintf(" %s %s\n", $marker, $name);
            }
        }

        // Help line (only if there's room)
        if ($height >= 15) {
            $output .= "\n";
            if ($width >= 70) {
                $output .= '  ' . $this->theme->styled('c', 'secondary') . $this->theme->dim(' Clear  ') .
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Select  ') .
                       $this->theme->styled('⏎', 'secondary') . $this->theme->dim(' Execute') . "\n";
            } else {
                $output .= '  ' . $this->theme->styled('c', 'secondary') . $this->theme->dim('Clear ') .
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim('Sel ') .
                       $this->theme->styled('⏎', 'secondary') . $this->theme->dim('Run') . "\n";
            }
        }

        return $output;
    }

    public function scrollUp(): void
    {
        if ($this->selectedAction > 0) {
            $this->selectedAction--;
        }
    }

    public function scrollDown(): void
    {
        if ($this->selectedAction < count($this->actions) - 1) {
            $this->selectedAction++;
        }
    }

    public function getSelectedAction(): ?array
    {
        return $this->actions[$this->selectedAction] ?? null;
    }

    public function executeSelectedAction(): bool
    {
        $action = $this->getSelectedAction();
        if (!$action) {
            return false;
        }

        try {
            \Artisan::call($action['key']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}