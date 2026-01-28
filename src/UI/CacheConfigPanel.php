<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Support\Theme;

class CacheConfigPanel
{
    private Theme $theme;
    private int $selectedAction = 0;
    private array $actions = [
        ['key' => 'cache:clear', 'name' => 'Clear Application Cache', 'desc' => 'Flush the application cache'],
        ['key' => 'config:clear', 'name' => 'Clear Config Cache', 'desc' => 'Remove the configuration cache file'],
        ['key' => 'view:clear', 'name' => 'Clear View Cache', 'desc' => 'Clear all compiled view files'],
        ['key' => 'route:clear', 'name' => 'Clear Route Cache', 'desc' => 'Remove the route cache file'],
        ['key' => 'optimize:clear', 'name' => 'Clear All Caches', 'desc' => 'Remove all cached data'],
    ];

    public function __construct(private CacheAdapter $adapter)
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $stats = $this->adapter->getCacheStats();
        
        $output = "\n";
        $output .= $this->theme->styled("  Cache Configuration\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────\n", 'muted');

        // Current cache info
        $output .= "\n";
        $output .= $this->theme->bold("  Current Configuration\n");
        $output .= sprintf("  %-20s %s\n", 'Cache Driver:', $this->theme->styled($stats['driver'], 'info'));
        $output .= sprintf("  %-20s %s\n", 'Cache Size:', $this->theme->styled($stats['size'], 'info'));
        $output .= sprintf("  %-20s %s\n", 'Status:', $this->theme->styled($stats['status'] ?? 'active', 'success'));

        // Show additional config based on driver
        try {
            $cacheConfig = config("cache.stores.{$stats['driver']}", []);
            if (!empty($cacheConfig)) {
                $output .= "\n";
                $output .= $this->theme->bold("  Driver Settings\n");
                foreach ($cacheConfig as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $output .= sprintf("  %-20s %s\n", ucfirst($key) . ':', $this->theme->dim((string)$value));
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $output .= "\n";
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────\n", 'muted');
        $output .= $this->theme->bold("  Available Actions\n");
        $output .= "\n";

        foreach ($this->actions as $i => $action) {
            $marker = ($i === $this->selectedAction) ? $this->theme->styled('▸ ', 'primary') : '  ';
            $name = ($i === $this->selectedAction) ? 
                $this->theme->styled($action['name'], 'primary') : 
                $action['name'];
            
            $output .= sprintf("%s%-35s %s\n", $marker, $name, $this->theme->dim($action['desc']));
        }

        $output .= "\n";
        $output .= $this->theme->dim("  Press c to clear cache, ↑/↓ to select action, Enter to execute\n");

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