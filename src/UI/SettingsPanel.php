<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Theme;

class SettingsPanel
{
    private Theme $theme;
    private int $selectedSetting = 0;

    public function __construct()
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $config = config('franken', []);
        
        $output = "\n";
        $output .= $this->theme->styled("  Settings\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        // Current settings
        $output .= "\n";
        $output .= $this->theme->bold("  Current Configuration\n");
        $output .= "\n";

        $settings = [
            ['Polling Interval', ($config['polling_interval'] ?? 2) . ' seconds', 'How often to refresh data'],
            ['Theme', $config['theme']['name'] ?? 'dark', 'Color theme for the UI'],
            ['Log Levels', implode(', ', array_slice($config['log_levels'] ?? [], 0, 4)) . '...', 'Log levels to display'],
        ];

        foreach ($settings as $i => $setting) {
            $marker = ($i === $this->selectedSetting) ? $this->theme->styled('▸ ', 'primary') : '  ';
            $name = ($i === $this->selectedSetting) ? 
                $this->theme->styled($setting[0], 'primary') : 
                $setting[0];
            
            $output .= sprintf(
                "%s%-25s %s\n",
                $marker,
                $name . ':',
                $this->theme->styled($setting[1], 'info')
            );
            $output .= sprintf("  %-25s %s\n", '', $this->theme->dim($setting[2]));
        }

        $output .= "\n";
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');
        $output .= $this->theme->bold("  Keybindings\n");
        $output .= "\n";

        $keybindings = $config['keybindings'] ?? [];
        $displayKeys = [
            ['quit', 'Quit application'],
            ['refresh', 'Refresh current panel'],
            ['search_logs', 'Search in logs'],
            ['navigate_up', 'Navigate up'],
            ['navigate_down', 'Navigate down'],
            ['clear_cache', 'Clear cache'],
            ['restart_worker', 'Restart queue worker'],
        ];

        foreach ($displayKeys as $keyInfo) {
            $key = $keyInfo[0];
            $desc = $keyInfo[1];
            $binding = $keybindings[$key] ?? 'N/A';
            
            $output .= sprintf(
                "  %-20s %s  %s\n",
                $desc,
                $this->theme->styled('[' . $binding . ']', 'primary'),
                ''
            );
        }

        // Tab switching
        $output .= "\n";
        $output .= $this->theme->bold("  Panel Shortcuts\n");
        $output .= "\n";

        $panels = [
            ['1', 'Overview'],
            ['2', 'Queues'],
            ['3', 'Jobs'],
            ['4', 'Logs'],
            ['5', 'Cache'],
            ['6', 'Scheduler'],
            ['7', 'Metrics'],
            ['8', 'Shell'],
            ['9', 'Settings'],
        ];

        $panelStr = '';
        foreach ($panels as $panel) {
            $panelStr .= $this->theme->styled('[' . $panel[0] . ']', 'primary') . ' ' . $panel[1] . '  ';
        }
        $output .= "  " . $panelStr . "\n";

        $output .= "\n";
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');
        $output .= $this->theme->bold("  About\n");
        $output .= "\n";
        
        $output .= "  " . $this->theme->styled("Franken-Console", 'primary') . " - A high-end TUI dashboard for Laravel\n";
        $output .= "  " . $this->theme->dim("Version: 1.0.0") . "\n";
        $output .= "  " . $this->theme->dim("PHP: " . PHP_VERSION) . "\n";
        
        try {
            $output .= "  " . $this->theme->dim("Laravel: " . app()->version()) . "\n";
        } catch (\Exception $e) {
            // Ignore
        }

        $output .= "\n";
        $output .= $this->theme->dim("  Configuration file: config/franken.php\n");

        return $output;
    }

    public function scrollUp(): void
    {
        if ($this->selectedSetting > 0) {
            $this->selectedSetting--;
        }
    }

    public function scrollDown(): void
    {
        if ($this->selectedSetting < 2) {
            $this->selectedSetting++;
        }
    }
}