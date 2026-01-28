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
    private int $terminalHeight = 24;

    public function __construct(private LogAdapter $adapter)
    {
        $this->theme = new Theme();
    }

    public function setTerminalHeight(int $height): void
    {
        $this->terminalHeight = $height;
    }

    public function render(): string
    {
        $logs = $this->adapter->getRecentLogs(100);

        // Filter logs if searching
        if ($this->searchMode && !empty($this->searchQuery)) {
            $logs = array_filter($logs, function($log) {
                return stripos($log['message'], $this->searchQuery) !== false ||
                       stripos($log['level'], $this->searchQuery) !== false;
            });
        }

        $this->filteredLogs = array_values($logs);

        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('LOG VIEWER', 'secondary'));
        
        if ($this->searchMode) {
            $output .= '   ' . $this->theme->styled('🔍 ', 'primary') . 
                       $this->theme->dim('Search: ') . 
                       $this->theme->styled($this->searchQuery, 'info') . 
                       $this->theme->styled('▌', 'primary');
        }
        
        $output .= "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', 76)) . "\n\n";

        // Column headers
        $output .= '  ' . $this->theme->dim(sprintf("  %-10s %-8s %s", 'TIME', 'LEVEL', 'MESSAGE')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', 76)) . "\n";

        $visibleLines = $this->getVisibleLines();
        $start = max(0, $this->scrollOffset);
        $end = min(count($this->filteredLogs), $start + $visibleLines);

        if (empty($this->filteredLogs)) {
            $output .= "\n" . '  ' . $this->theme->dim('  No logs found');
            if ($this->searchMode) {
                $output .= $this->theme->dim(" matching '") . 
                           $this->theme->styled($this->searchQuery, 'warning') . 
                           $this->theme->dim("'");
            }
            $output .= "\n";
        } else {
            for ($i = $start; $i < $end; $i++) {
                $log = $this->filteredLogs[$i];
                $isSelected = ($i === $this->selectedIndex);
                
                $marker = $isSelected ? $this->theme->styled(' ▸', 'primary') : '  ';

                $levelColor = $this->getLevelColor($log['level']);
                $levelBadge = $this->formatLevelBadge($log['level']);

                // Truncate long messages
                $message = $log['message'];
                $maxMsgLen = 55;
                if (strlen($message) > $maxMsgLen) {
                    $message = substr($message, 0, $maxMsgLen) . $this->theme->dim('…');
                }

                // Highlight search query in message
                if ($this->searchMode && !empty($this->searchQuery)) {
                    $message = $this->highlightSearch($message, $this->searchQuery);
                }

                $timestamp = $this->formatTimestamp($log['timestamp']);

                $output .= sprintf(
                    "%s %-10s %s %s\n",
                    $marker,
                    $this->theme->dim($timestamp),
                    $this->theme->styled($levelBadge, $levelColor),
                    $isSelected ? $this->theme->bold($message) : $message
                );
            }
        }

        // Status bar
        $output .= "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', 76)) . "\n";
        
        $totalLogs = count($this->filteredLogs);
        $showing = min($end - $start, $totalLogs);
        
        $statusParts = [];
        $statusParts[] = $this->theme->dim("Showing ") . $this->theme->styled((string)$showing, 'info') . 
                        $this->theme->dim(" of ") . $this->theme->styled((string)$totalLogs, 'info');
        
        if ($this->scrollOffset > 0) {
            $statusParts[] = $this->theme->dim('↑ scroll up');
        }
        if ($end < count($this->filteredLogs)) {
            $statusParts[] = $this->theme->dim('↓ scroll down');
        }

        $output .= '  ' . implode('  ', $statusParts) . "\n";
        $output .= "\n";
        $output .= '  ' . $this->theme->dim('  ') .
                   $this->theme->styled('/', 'secondary') . $this->theme->dim(' Search  ') .
                   $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Navigate  ') .
                   $this->theme->styled('PgUp/Dn', 'secondary') . $this->theme->dim(' Page  ') .
                   $this->theme->styled('Home/End', 'secondary') . $this->theme->dim(' Jump') . "\n";

        return $output;
    }

    private function getLevelColor(string $level): string
    {
        return match(strtolower($level)) {
            'emergency', 'alert', 'critical', 'error' => 'error',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            'debug' => 'muted',
            default => 'foreground',
        };
    }

    private function formatLevelBadge(string $level): string
    {
        $level = strtoupper(substr($level, 0, 5));
        return sprintf('[%-5s]', $level);
    }

    private function formatTimestamp(string $timestamp): string
    {
        try {
            $dt = new \DateTime($timestamp);
            return $dt->format('H:i:s');
        } catch (\Exception $e) {
            return substr($timestamp, 11, 8);
        }
    }

    private function highlightSearch(string $text, string $query): string
    {
        $pos = stripos($text, $query);
        if ($pos === false) {
            return $text;
        }

        $before = substr($text, 0, $pos);
        $match = substr($text, $pos, strlen($query));
        $after = substr($text, $pos + strlen($query));

        return $before . $this->theme->styled($match, 'warning') . $after;
    }

    private function getVisibleLines(): int
    {
        return max(5, $this->terminalHeight - 10);
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
        $pageSize = $this->getVisibleLines();
        $this->selectedIndex = max(0, $this->selectedIndex - $pageSize);
        $this->adjustScroll();
    }

    public function pageDown(): void
    {
        $pageSize = $this->getVisibleLines();
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

    public function exitSearchMode(): void
    {
        $this->searchMode = false;
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
        if (strlen($this->searchQuery) > 0) {
            $this->searchQuery = substr($this->searchQuery, 0, -1);
            $this->selectedIndex = 0;
            $this->scrollOffset = 0;
        }
    }

    private function adjustScroll(): void
    {
        $visibleLines = $this->getVisibleLines();

        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $visibleLines) {
            $this->scrollOffset = $this->selectedIndex - $visibleLines + 1;
        }
    }
}