<?php

declare(strict_types=1);

namespace Franken\Console\Support;

/**
 * Cross-platform terminal handling for Windows, Unix, and SSH sessions.
 */
class Terminal
{
    private bool $isWindows;
    private bool $isSSH;
    private bool $rawModeEnabled = false;
    private ?string $originalSttySettings = null;
    
    // Cache dimensions for performance, but allow refresh on resize
    private ?int $cachedWidth = null;
    private ?int $cachedHeight = null;
    private float $lastDimensionCheck = 0;
    private const DIMENSION_CACHE_TTL = 0.25; // Refresh every 250ms for SSH responsiveness

    public function __construct()
    {
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Detect if running in SSH session
        $this->isSSH = $this->detectSSH();
    }

    /**
     * Detect if we're running in an SSH session.
     */
    private function detectSSH(): bool
    {
        // Check common SSH environment variables
        if (getenv('SSH_CLIENT') !== false) {
            return true;
        }
        if (getenv('SSH_TTY') !== false) {
            return true;
        }
        if (getenv('SSH_CONNECTION') !== false) {
            return true;
        }
        return false;
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

        if ($this->isWindows && !$this->isSSH) {
            // On Windows local console, we can't use stty
            stream_set_blocking(STDIN, false);
        } else {
            // Unix or SSH session - use stty
            $this->originalSttySettings = shell_exec('stty -g 2>/dev/null');
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

        if ($this->isWindows && !$this->isSSH) {
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
        $this->cachedWidth = $this->detectWidth();

        return $this->cachedWidth;
    }

    /**
     * Detect terminal width using multiple methods.
     */
    private function detectWidth(): int
    {
        // Method 1: Try stty (works on Unix and SSH)
        $output = @shell_exec('stty size 2>/dev/null');
        if ($output !== null && preg_match('/\d+\s+(\d+)/', trim($output), $matches)) {
            return (int) $matches[1];
        }

        // Method 2: Try tput (Unix)
        $output = @shell_exec('tput cols 2>/dev/null');
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        // Method 3: Check COLUMNS environment variable
        $cols = getenv('COLUMNS');
        if ($cols !== false && is_numeric($cols)) {
            return (int) $cols;
        }

        // Method 4: Windows local console only
        if ($this->isWindows && !$this->isSSH) {
            $output = @shell_exec('mode con 2>nul');
            if ($output !== null && preg_match('/Columns:\s*(\d+)/i', $output, $matches)) {
                return (int) $matches[1];
            }
        }

        // Method 5: Try resize command (available on some systems)
        $output = @shell_exec('resize 2>/dev/null | grep COLUMNS');
        if ($output !== null && preg_match('/COLUMNS=(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        // Fallback - sensible default for most terminals
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
        $this->cachedHeight = $this->detectHeight();

        return $this->cachedHeight;
    }

    /**
     * Detect terminal height using multiple methods.
     */
    private function detectHeight(): int
    {
        // Method 1: Try stty (works on Unix and SSH)
        $output = @shell_exec('stty size 2>/dev/null');
        if ($output !== null && preg_match('/(\d+)\s+\d+/', trim($output), $matches)) {
            return (int) $matches[1];
        }

        // Method 2: Try tput (Unix)
        $output = @shell_exec('tput lines 2>/dev/null');
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        // Method 3: Check LINES environment variable
        $lines = getenv('LINES');
        if ($lines !== false && is_numeric($lines)) {
            return (int) $lines;
        }

        // Method 4: Windows local console only
        if ($this->isWindows && !$this->isSSH) {
            $output = @shell_exec('mode con 2>nul');
            if ($output !== null && preg_match('/Lines:\s*(\d+)/i', $output, $matches)) {
                return (int) $matches[1];
            }
        }

        // Method 5: Try resize command
        $output = @shell_exec('resize 2>/dev/null | grep LINES');
        if ($output !== null && preg_match('/LINES=(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        // Fallback
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
     * Check if running in SSH session.
     */
    public function isSSHSession(): bool
    {
        return $this->isSSH;
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
