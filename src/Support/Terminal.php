<?php

declare(strict_types=1);

namespace Franken\Console\Support;

/**
 * Cross-platform terminal handling for Windows and Unix systems.
 */
class Terminal
{
    private bool $isWindows;
    private bool $rawModeEnabled = false;
    private ?string $originalSttySettings = null;
    
    // Cache dimensions for performance, but allow refresh on resize
    private ?int $cachedWidth = null;
    private ?int $cachedHeight = null;
    private float $lastDimensionCheck = 0;
    private const DIMENSION_CACHE_TTL = 0.5; // Refresh dimensions every 0.5 seconds

    public function __construct()
    {
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Force refresh of terminal dimensions.
     * Call this before rendering after potential resize.
     */
    public function refreshDimensions(): void
    {
        $this->cachedWidth = null;
        $this->cachedHeight = null;
        $this->lastDimensionCheck = 0;
    }

    /**
     * Enable raw mode for reading individual keypresses.
     */
    public function enableRawMode(): void
    {
        if ($this->rawModeEnabled) {
            return;
        }

        if ($this->isWindows) {
            // On Windows, we can't use stty but stream_set_blocking helps
            stream_set_blocking(STDIN, false);
        } else {
            // Save current settings
            $this->originalSttySettings = shell_exec('stty -g 2>/dev/null');
            
            // Enable raw mode
            system('stty raw -echo 2>/dev/null');
        }

        $this->rawModeEnabled = true;
    }

    /**
     * Disable raw mode and restore terminal.
     */
    public function disableRawMode(): void
    {
        if (!$this->rawModeEnabled) {
            return;
        }

        if ($this->isWindows) {
            stream_set_blocking(STDIN, true);
        } else {
            if ($this->originalSttySettings) {
                system('stty ' . trim($this->originalSttySettings) . ' 2>/dev/null');
            } else {
                system('stty sane 2>/dev/null');
            }
        }

        $this->rawModeEnabled = false;
    }

    /**
     * Enter alternate screen buffer (like vim does).
     */
    public function enterAlternateScreen(): void
    {
        echo "\033[?1049h"; // Enter alternate screen
        echo "\033[2J";     // Clear screen
        echo "\033[H";      // Move cursor to home
    }

    /**
     * Exit alternate screen buffer.
     */
    public function exitAlternateScreen(): void
    {
        echo "\033[?1049l"; // Exit alternate screen
    }

    /**
     * Hide the cursor.
     */
    public function hideCursor(): void
    {
        echo "\033[?25l";
    }

    /**
     * Show the cursor.
     */
    public function showCursor(): void
    {
        echo "\033[?25h";
    }

    /**
     * Clear the screen.
     */
    public function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    /**
     * Check if dimensions cache should be refreshed.
     */
    private function shouldRefreshDimensions(): bool
    {
        return (microtime(true) - $this->lastDimensionCheck) >= self::DIMENSION_CACHE_TTL;
    }

    /**
     * Get terminal width.
     */
    public function getWidth(): int
    {
        if ($this->cachedWidth !== null && !$this->shouldRefreshDimensions()) {
            return $this->cachedWidth;
        }

        $this->lastDimensionCheck = microtime(true);

        if ($this->isWindows) {
            $this->cachedWidth = $this->getWindowsWidth();
        } else {
            $this->cachedWidth = $this->getUnixWidth();
        }

        return $this->cachedWidth;
    }

    private function getWindowsWidth(): int
    {
        // Method 1: MODE command (fastest and most reliable on Windows)
        $output = shell_exec('mode con 2>nul');
        if ($output !== null && preg_match('/Columns:\s*(\d+)/i', $output, $matches)) {
            return (int) $matches[1];
        }

        // Method 2: PowerShell (slower but reliable)
        $output = shell_exec('powershell -NoProfile -Command "[Console]::WindowWidth" 2>nul');
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        // Fallback
        return 120;
    }

    private function getUnixWidth(): int
    {
        // Try tput first (most reliable)
        $output = shell_exec('tput cols 2>/dev/null');
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        // Try stty
        $output = shell_exec('stty size 2>/dev/null');
        if ($output !== null && preg_match('/\d+\s+(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        // Check environment
        $cols = getenv('COLUMNS');
        if ($cols !== false && is_numeric($cols)) {
            return (int) $cols;
        }

        return 80;
    }

    /**
     * Get terminal height.
     */
    public function getHeight(): int
    {
        if ($this->cachedHeight !== null && !$this->shouldRefreshDimensions()) {
            return $this->cachedHeight;
        }

        $this->lastDimensionCheck = microtime(true);

        if ($this->isWindows) {
            $this->cachedHeight = $this->getWindowsHeight();
        } else {
            $this->cachedHeight = $this->getUnixHeight();
        }

        return $this->cachedHeight;
    }

    private function getWindowsHeight(): int
    {
        // Method 1: MODE command (fastest)
        $output = shell_exec('mode con 2>nul');
        if ($output !== null && preg_match('/Lines:\s*(\d+)/i', $output, $matches)) {
            return (int) $matches[1];
        }

        // Method 2: PowerShell
        $output = shell_exec('powershell -NoProfile -Command "[Console]::WindowHeight" 2>nul');
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        // Fallback
        return 30;
    }

    private function getUnixHeight(): int
    {
        // Try tput first
        $output = shell_exec('tput lines 2>/dev/null');
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        // Try stty
        $output = shell_exec('stty size 2>/dev/null');
        if ($output !== null && preg_match('/(\d+)\s+\d+/', $output, $matches)) {
            return (int) $matches[1];
        }

        // Check environment
        $lines = getenv('LINES');
        if ($lines !== false && is_numeric($lines)) {
            return (int) $lines;
        }

        return 24;
    }

    /**
     * Check if running on Windows.
     */
    public function isWindows(): bool
    {
        return $this->isWindows;
    }

    /**
     * Get dimensions as array [width, height].
     */
    public function getDimensions(): array
    {
        return [$this->getWidth(), $this->getHeight()];
    }

    /**
     * Move cursor to position.
     */
    public function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    /**
     * Save cursor position.
     */
    public function saveCursor(): void
    {
        echo "\033[s";
    }

    /**
     * Restore cursor position.
     */
    public function restoreCursor(): void
    {
        echo "\033[u";
    }
}
