<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Theme;

class SettingsPanel
{
    private Theme $theme;
    private int $selectedSetting = 0;
    private int $terminalHeight = 24;
    private int $terminalWidth = 80;

    public function __construct()
    {
        $this->theme = new Theme();
    }

    public function setTerminalHeight(int $height): void
    {
        $this->terminalHeight = $height;
    }

    public function setTerminalWidth(int $width): void
    {
        $this->terminalWidth = $width;
    }

    public function render(): string
    {
        $width = $this->terminalWidth;
        $height = $this->terminalHeight;
        $lineWidth = max(40, $width - 4);
        
        $config = config('franken', []);
        
        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('SETTINGS', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        $output .= '  ' . $this->theme->bold('Current Configuration') . "\n";

        $settings = [
            ['Polling Interval', ($config['polling_interval'] ?? 2) . 's', 'Refresh interval'],
            ['Theme', $config['theme']['name'] ?? 'dark', 'UI theme'],
            ['Log Levels', count($config['log_levels'] ?? []) . ' levels', 'Active levels'],
        ];

        foreach ($settings as $i => $setting) {
            $marker = ($i === $this->selectedSetting) ? $this->theme->styled('▸', 'primary') : ' ';
            $name = ($i === $this->selectedSetting) ? 
                $this->theme->styled($setting[0], 'primary') : 
                $setting[0];
            
            if ($width >= 80) {
                $output .= sprintf(" %s %-20s %s  %s\n",
                    $marker, $name . ':', $this->theme->styled($setting[1], 'info'), $this->theme->dim($setting[2])
                );
            } else {
                $output .= sprintf(" %s %-16s %s\n",
                    $marker, $name . ':', $this->theme->styled($setting[1], 'info')
                );
            }
        }

        // Keybindings (only if there's room)
        if ($height >= 18) {
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            $output .= '  ' . $this->theme->bold('Keybindings') . "\n";

            $keybindings = $config['keybindings'] ?? [];
            $displayKeys = [
                ['quit', 'Quit', 'q'],
                ['refresh', 'Refresh', 'r'],
                ['search_logs', 'Search', '/'],
                ['navigate_up', 'Up', '↑'],
                ['navigate_down', 'Down', '↓'],
            ];

            if ($width >= 70) {
                // Horizontal layout
                $output .= '  ';
                foreach ($displayKeys as $keyInfo) {
                    $binding = $keybindings[$keyInfo[0]] ?? $keyInfo[2];
                    $output .= $this->theme->styled('[' . $binding . ']', 'primary') . ' ' . $this->theme->dim($keyInfo[1]) . '  ';
                }
                $output .= "\n";
            } else {
                // Compact grid
                $perRow = 3;
                $count = 0;
                $output .= '  ';
                foreach ($displayKeys as $keyInfo) {
                    $binding = $keybindings[$keyInfo[0]] ?? $keyInfo[2];
                    $output .= $this->theme->styled('[' . $binding . ']', 'primary') . $this->theme->dim($keyInfo[1]) . ' ';
                    $count++;
                    if ($count % $perRow === 0) {
                        $output .= "\n  ";
                    }
                }
                $output .= "\n";
            }
        }

        // Panel shortcuts (only if there's room)
        if ($height >= 22) {
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            $output .= '  ' . $this->theme->bold('Panel Shortcuts') . "\n";

            $panels = [
                ['1', 'Overview'], ['2', 'Queues'], ['3', 'Jobs'], ['4', 'Logs'], ['5', 'Cache'],
                ['6', 'Scheduler'], ['7', 'Metrics'], ['8', 'Shell'], ['9', 'Settings'],
            ];

            if ($width >= 80) {
                $output .= '  ';
                foreach ($panels as $panel) {
                    $output .= $this->theme->styled('[' . $panel[0] . ']', 'primary') . ' ' . $panel[1] . '  ';
                }
                $output .= "\n";
            } else {
                $output .= '  ';
                foreach ($panels as $panel) {
                    $output .= $this->theme->styled($panel[0], 'primary') . ':' . substr($panel[1], 0, 3) . ' ';
                }
                $output .= "\n";
            }
        }

        // About section (only if there's room)
        if ($height >= 26) {
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            $output .= '  ' . $this->theme->bold('About') . "\n";
            
            $output .= '  ' . $this->theme->styled('Franken-Console', 'primary') . ' v1.0.0';
            $output .= ' | PHP ' . PHP_VERSION;
            
            try {
                $output .= ' | Laravel ' . app()->version();
            } catch (\Exception $e) {
                // Ignore
            }
            $output .= "\n";
        }

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