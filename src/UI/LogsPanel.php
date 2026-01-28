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
    private int $terminalWidth = 80;

    public function __construct(private LogAdapter $adapter)
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
        $logs = $this->adapter->getRecentLogs(100);

        // Filter logs if searching
        if ($this->searchMode && !empty($this->searchQuery)) {
            $logs = array_filter($logs, function($log) {
                return stripos($log['message'], $this->searchQuery) !== false ||
                       stripos($log['level'], $this->searchQuery) !== false;
            });
        }

        $this->filteredLogs = array_values($logs);
        $lineWidth = max(40, $width - 4);
        $maxMsgLen = max(20, $width - 30);

        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('LOGS', 'secondary'));
        
        if ($this->searchMode) {
            $output .= '  ' . $this->theme->styled('/', 'primary') . 
                       $this->theme->styled($this->searchQuery, 'info') . 
                       $this->theme->styled('▌', 'primary');
        }
        
        $output .= "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        // Column headers - responsive
        if ($width >= 80) {
            $output .= '  ' . $this->theme->dim(sprintf("  %-10s %-7s %s", 'TIME', 'LEVEL', 'MESSAGE')) . "\n";
        } else {
            $output .= '  ' . $this->theme->dim(sprintf("  %-8s %-5s %s", 'TIME', 'LVL', 'MESSAGE')) . "\n";
        }
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

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
                
                $marker = $isSelected ? $this->theme->styled('▸', 'primary') : ' ';

                $levelColor = $this->getLevelColor($log['level']);
                $levelBadge = $this->formatLevelBadge($log['level'], $width < 80);

                // Truncate long messages based on terminal width
                $message = $log['message'];
                if (strlen($message) > $maxMsgLen) {
                    $message = substr($message, 0, $maxMsgLen) . $this->theme->dim('…');
                }

                // Highlight search query in message
                if ($this->searchMode && !empty($this->searchQuery)) {
                    $message = $this->highlightSearch($message, $this->searchQuery);
                }

                $timestamp = $this->formatTimestamp($log['timestamp']);

                $output .= sprintf(
                    "  %s %-8s %s %s\n",
                    $marker,
                    $this->theme->dim($timestamp),
                    $this->theme->styled($levelBadge, $levelColor),
                    $isSelected ? $this->theme->bold($message) : $message
                );
            }
        }

        // Status bar with page numbers
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        
        $totalLogs = count($this->filteredLogs);
        $visibleLines = $this->getVisibleLines();
        $totalPages = max(1, (int)ceil($totalLogs / $visibleLines));
        $currentPage = (int)floor($this->scrollOffset / max(1, $visibleLines)) + 1;
        
        // Page info
        $pageInfo = $this->theme->dim('Page ') . 
                   $this->theme->styled((string)$currentPage, 'info') . 
                   $this->theme->dim('/') . 
                   $this->theme->styled((string)$totalPages, 'info');
        
        $countInfo = $this->theme->dim(' (') . 
                    $this->theme->styled((string)$totalLogs, 'info') . 
                    $this->theme->dim(' logs)');
        
        $scrollIndicators = '';
        if ($this->scrollOffset > 0) {
            $scrollIndicators .= ' ' . $this->theme->dim('↑');
        }
        if ($end < count($this->filteredLogs)) {
            $scrollIndicators .= ' ' . $this->theme->dim('↓');
        }

        $output .= '  ' . $pageInfo . $countInfo . $scrollIndicators . "\n";
        
        // Responsive help line (only if there's room)
        if ($this->terminalHeight >= 15) {
            if ($width >= 80) {
                $output .= '  ' .
                       $this->theme->styled('/', 'secondary') . $this->theme->dim('Search ') .
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim('Nav ') .
                       $this->theme->styled('PgUp/Dn', 'secondary') . $this->theme->dim('Page') . "\n";
            } else {
                $output .= '  ' .
                       $this->theme->styled('/', 'secondary') . $this->theme->dim('Srch ') .
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim('Nav') . "\n";
            }
        }

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

    private function formatLevelBadge(string $level, bool $compact = false): string
    {
        if ($compact) {
            return strtoupper(substr($level, 0, 3));
        }
        $level = strtoupper(substr($level, 0, 5));
        return sprintf('%-5s', $level);
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