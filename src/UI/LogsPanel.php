<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\LogAdapter;
use Franken\Console\Support\Theme;

class LogsPanel
{
    private int $scrollOffset = 0;
    private int $selectedIndex = 0;
    private bool $searchMode = false;
    private string $searchQuery = '';
    private array $filteredLogs = [];
    private Theme $theme;

    public function __construct(private LogAdapter $adapter)
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $logs = $this->adapter->getRecentLogs(100); // Get more logs for scrolling

        // Filter logs if searching
        if ($this->searchMode && !empty($this->searchQuery)) {
            $logs = array_filter($logs, function($log) {
                return stripos($log['message'], $this->searchQuery) !== false ||
                       stripos($log['level'], $this->searchQuery) !== false;
            });
        }

        $this->filteredLogs = array_values($logs); // Re-index

        $output = $this->theme->styled("Logs", 'primary') . "\n";

        if ($this->searchMode) {
            $output .= "Search: {$this->searchQuery}_\n\n";
        }

        $terminalHeight = $this->getTerminalHeight();
        $visibleLines = $terminalHeight - 5; // Leave space for header and footer

        $start = max(0, $this->scrollOffset);
        $end = min(count($this->filteredLogs), $start + $visibleLines);

        for ($i = $start; $i < $end; $i++) {
            $log = $this->filteredLogs[$i];
            $marker = ($i === $this->selectedIndex) ? $this->theme->styled(">> ", 'secondary') : "   ";

            $color = match($log['level']) {
                'error', 'critical', 'alert', 'emergency' => 'error',
                'warning' => 'warning',
                'info', 'notice' => 'info',
                'debug' => 'muted',
                default => 'foreground',
            };

            $level = strtoupper($log['level']);
            $output .= "$marker" . $this->theme->styled("[$level]", $color) . " {$log['message']}\n";
        }

        // Add scroll indicators
        if ($this->scrollOffset > 0) {
            $output .= "\n" . $this->theme->dim("↑ More logs above");
        }
        if ($end < count($this->filteredLogs)) {
            $output .= "\n" . $this->theme->dim("↓ More logs below");
        }

        $output .= "\n" . $this->theme->dim("Use ↑↓ to navigate, / to search, q to quit");

        return $output;
    }

    public function scrollUp(): void
    {
        if ($this->selectedIndex > 0) {
            $this->selectedIndex--;
            $this->adjustScroll();
        }
    }

    public function scrollDown(): void
    {
        if ($this->selectedIndex < count($this->filteredLogs) - 1) {
            $this->selectedIndex++;
            $this->adjustScroll();
        }
    }

    public function pageUp(): void
    {
        $pageSize = $this->getTerminalHeight() - 5;
        $this->selectedIndex = max(0, $this->selectedIndex - $pageSize);
        $this->adjustScroll();
    }

    public function pageDown(): void
    {
        $pageSize = $this->getTerminalHeight() - 5;
        $maxIndex = count($this->filteredLogs) - 1;
        $this->selectedIndex = min($maxIndex, $this->selectedIndex + $pageSize);
        $this->adjustScroll();
    }

    public function scrollToTop(): void
    {
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;
    }

    public function scrollToBottom(): void
    {
        $this->selectedIndex = max(0, count($this->filteredLogs) - 1);
        $this->adjustScroll();
    }

    public function enterSearchMode(): void
    {
        $this->searchMode = true;
        $this->searchQuery = '';
    }

    public function isInSearchMode(): bool
    {
        return $this->searchMode;
    }

    public function addSearchChar(string $char): void
    {
        $this->searchQuery .= $char;
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;
    }

    public function removeSearchChar(): void
    {
        $this->searchQuery = substr($this->searchQuery, 0, -1);
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;
    }

    private function adjustScroll(): void
    {
        $visibleLines = $this->getTerminalHeight() - 5;

        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $visibleLines) {
            $this->scrollOffset = $this->selectedIndex - $visibleLines + 1;
        }
    }

    private function getTerminalHeight(): int
    {
        // Default fallback
        return 24;
    }
}