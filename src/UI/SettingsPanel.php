<?php

declare(strict_types=1);

namespace Franken\Console\UI;

class SettingsPanel
{
    public function render(): string
    {
        $config = config('franken');
        $output = "\033[35mSettings:\033[0m\n";
        $output .= "Polling Interval: {$config['polling_interval']}s\n";
        $output .= "Log Levels: " . implode(', ', $config['log_levels']) . "\n";
        $output .= "Theme: " . json_encode($config['theme']) . "\n";
        $output .= "Keybindings: " . json_encode($config['keybindings']) . "\n";
        return $output;
    }
}