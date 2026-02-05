<?php

declare(strict_types=1);

namespace Franken\Console\Support;

class Theme
{
    private array $colors;
    private string $themeName;

    public function __construct(?string $themeName = null)
    {
        $this->themeName = $themeName ?? 'dark';
        
        // Use default colors
        $this->colors = $this->getDefaultColors();
    }

    private function getDefaultColors(): array
    {
        return [
            'primary' => 'cyan',
            'secondary' => 'yellow',
            'error' => 'red',
            'success' => 'green',
            'warning' => 'yellow',
            'info' => 'blue',
            'muted' => 'gray',
            'background' => 'black',
            'foreground' => 'white',
        ];
    }

    public function color(string $name): string
    {
        return $this->colors[$name] ?? 'white';
    }

    public function ansi(string $name): string
    {
        $colorName = $this->color($name);
        return $this->colorToAnsi($colorName);
    }

    private function colorToAnsi(string $color): string
    {
        return match($color) {
            'black' => "\033[30m",
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'gray', 'grey' => "\033[90m",
            'bright_red' => "\033[91m",
            'bright_green' => "\033[92m",
            'bright_yellow' => "\033[93m",
            'bright_blue' => "\033[94m",
            'bright_magenta' => "\033[95m",
            'bright_cyan' => "\033[96m",
            'bright_white' => "\033[97m",
            default => "\033[0m",
        };
    }

    public function styled(string $text, string $colorName): string
    {
        return $this->ansi($colorName) . $text . "\033[0m";
    }

    public function dim(string $text): string
    {
        return "\033[2m" . $text . "\033[0m";
    }

    public function bold(string $text): string
    {
        return "\033[1m" . $text . "\033[0m";
    }

    public function underline(string $text): string
    {
        return "\033[4m" . $text . "\033[0m";
    }

    public function italic(string $text): string
    {
        return "\033[3m" . $text . "\033[0m";
    }

    public function strikethrough(string $text): string
    {
        return "\033[9m" . $text . "\033[0m";
    }

    public function inverse(string $text): string
    {
        return "\033[7m" . $text . "\033[0m";
    }

    public function reset(): string
    {
        return "\033[0m";
    }

    /**
     * Create a box around text
     */
    public function box(string $text, int $width = 0): string
    {
        $lines = explode("\n", $text);
        $maxLen = $width ?: max(array_map('mb_strlen', $lines));

        $output = '┌' . str_repeat('─', $maxLen + 2) . '┐' . "\n";
        foreach ($lines as $line) {
            $padding = $maxLen - mb_strlen($line);
            $output .= '│ ' . $line . str_repeat(' ', $padding) . ' │' . "\n";
        }
        $output .= '└' . str_repeat('─', $maxLen + 2) . '┘';

        return $output;
    }

    /**
     * Create a progress bar
     */
    public function progressBar(float $percent, int $width = 20): string
    {
        $filled = (int) round(($percent / 100) * $width);
        $empty = $width - $filled;

        $color = $percent > 80 ? 'error' : ($percent > 60 ? 'warning' : 'success');

        return $this->styled(str_repeat('█', $filled), $color) . 
               $this->styled(str_repeat('░', $empty), 'muted');
    }

    // ========================================
    // Tab styling methods (Solo-style)
    // ========================================

    /**
     * Style for a focused (active) tab
     */
    public function tabFocused(string $text, string $state): string
    {
        $indicator = $this->tabIndicator($state);
        
        // White background with black text for focused tab (high contrast)
        return "\033[47;30m" . $indicator . ltrim($text) . "\033[0m";
    }

    /**
     * Style for a blurred (inactive) tab
     */
    public function tabBlurred(string $text, string $state): string
    {
        $indicator = $this->tabIndicator($state);
        return $indicator . $this->dim(ltrim($text));
    }

    /**
     * Style for tab overflow indicators (like "← 2" or "3 →")
     */
    public function tabMore(string $text): string
    {
        return $this->dim($text);
    }

    /**
     * Generate tab state indicator (dot with color)
     */
    public function tabIndicator(string $state): string
    {
        return match ($state) {
            'running', 'focused' => $this->styled('•', 'success'),
            'stopped' => $this->styled('•', 'error'),
            'paused' => $this->styled('•', 'warning'),
            default => $this->styled('•', 'muted'),
        };
    }

    // ========================================
    // Process/Content styling methods
    // ========================================

    /**
     * Style for stopped process indicator
     */
    public function processStopped(string $text): string
    {
        return $this->styled($text, 'error');
    }

    /**
     * Style for running process indicator
     */
    public function processRunning(string $text): string
    {
        return $this->styled($text, 'success');
    }

    /**
     * Style for paused logs
     */
    public function logsPaused(string $text): string
    {
        return $this->styled($text, 'warning');
    }

    /**
     * Style for live logs
     */
    public function logsLive(string $text): string
    {
        return $this->dim($text);
    }

    // ========================================
    // UI component styling methods
    // ========================================

    /**
     * Style for hotkey indicators
     */
    public function hotkey(string $text): string
    {
        return $this->styled($text, 'primary');
    }

    /**
     * Style for hotkey labels
     */
    public function hotkeyLabel(string $text): string
    {
        return $this->styled($text, 'muted');
    }

    /**
     * Style for border elements
     */
    public function border(): string
    {
        return $this->color('muted');
    }

    /**
     * Style for content area
     */
    public function content(): string
    {
        return $this->color('foreground');
    }

    /**
     * Style for status indicators
     */
    public function status(): string
    {
        return $this->color('info');
    }
}