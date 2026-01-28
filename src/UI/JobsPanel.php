<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Support\Theme;

class JobsPanel
{
    private Theme $theme;
    private int $scrollOffset = 0;
    private int $selectedIndex = 0;
    private array $jobs = [];
    private int $terminalHeight = 24;
    private int $terminalWidth = 80;

    public function __construct(private QueueAdapter $adapter)
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

    private function getVisibleCount(): int
    {
        // Reserve lines for: header, separator, column header, separator, status, help
        return max(3, $this->terminalHeight - 10);
    }

    public function render(): string
    {
        $width = $this->terminalWidth;
        $height = $this->terminalHeight;
        $lineWidth = max(40, $width - 4);
        
        // Get jobs from adapter
        $this->jobs = $this->adapter->getRecentJobs(50);
        
        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('RECENT JOBS', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        // Responsive header
        if ($width >= 100) {
            $output .= sprintf(
                "  %-8s %-35s %-14s %-18s\n",
                $this->theme->dim('ID'),
                $this->theme->dim('JOB CLASS'),
                $this->theme->dim('STATUS'),
                $this->theme->dim('PROCESSED')
            );
        } elseif ($width >= 70) {
            $output .= sprintf(
                "  %-6s %-25s %-12s %-15s\n",
                $this->theme->dim('ID'),
                $this->theme->dim('CLASS'),
                $this->theme->dim('STATUS'),
                $this->theme->dim('TIME')
            );
        } else {
            $output .= sprintf(
                "  %-5s %-15s %-10s\n",
                $this->theme->dim('ID'),
                $this->theme->dim('CLASS'),
                $this->theme->dim('STATUS')
            );
        }
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        if (empty($this->jobs)) {
            $output .= '  ' . $this->theme->dim('No recent jobs found') . "\n";
        } else {
            $visibleCount = $this->getVisibleCount();
            $start = $this->scrollOffset;
            $end = min($start + $visibleCount, count($this->jobs));
            
            // Calculate page info
            $totalPages = (int)ceil(count($this->jobs) / $visibleCount);
            $currentPage = (int)floor($this->scrollOffset / $visibleCount) + 1;

            for ($i = $start; $i < $end; $i++) {
                $job = $this->jobs[$i];
                $isSelected = ($i === $this->selectedIndex);
                $marker = $isSelected ? $this->theme->styled('▸', 'primary') : ' ';
                
                $statusIcon = match($job['status']) {
                    'processed' => $this->theme->styled('✓', 'success'),
                    'failed' => $this->theme->styled('✗', 'error'),
                    'pending' => $this->theme->styled('◌', 'warning'),
                    'processing' => $this->theme->styled('◉', 'info'),
                    default => $this->theme->dim('?'),
                };
                
                $statusText = match($job['status']) {
                    'processed' => $this->theme->styled('Done', 'success'),
                    'failed' => $this->theme->styled('Fail', 'error'),
                    'pending' => $this->theme->styled('Wait', 'warning'),
                    'processing' => $this->theme->styled('Run', 'info'),
                    default => $this->theme->dim($job['status']),
                };

                // Truncate job class name based on width
                $jobClass = $job['class'];
                $maxClassLen = $width >= 100 ? 33 : ($width >= 70 ? 23 : 13);
                if (strlen($jobClass) > $maxClassLen) {
                    $jobClass = '…' . substr($jobClass, -($maxClassLen - 1));
                }
                
                $jobId = $isSelected 
                    ? $this->theme->styled((string)$job['id'], 'primary')
                    : (string)$job['id'];

                if ($width >= 100) {
                    $output .= sprintf(
                        " %s %-8s %-35s %s %-10s %-18s\n",
                        $marker,
                        $jobId,
                        $jobClass,
                        $statusIcon,
                        $statusText,
                        $this->theme->dim($job['processed_at'])
                    );
                } elseif ($width >= 70) {
                    $output .= sprintf(
                        " %s %-6s %-25s %s %-8s %-15s\n",
                        $marker,
                        $jobId,
                        $jobClass,
                        $statusIcon,
                        $statusText,
                        $this->theme->dim($job['processed_at'])
                    );
                } else {
                    $output .= sprintf(
                        " %s %-5s %-15s %s %s\n",
                        $marker,
                        $jobId,
                        $jobClass,
                        $statusIcon,
                        $statusText
                    );
                }
            }

            // Page indicator and scroll status
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            
            $pageInfo = $this->theme->dim('Page ') . 
                       $this->theme->styled((string)$currentPage, 'info') . 
                       $this->theme->dim('/') . 
                       $this->theme->styled((string)$totalPages, 'info');
            
            $countInfo = $this->theme->dim(' (') . 
                        $this->theme->styled((string)count($this->jobs), 'info') . 
                        $this->theme->dim(' jobs)');
            
            $scrollIndicators = '';
            if ($this->scrollOffset > 0) {
                $scrollIndicators .= $this->theme->dim(' ↑');
            }
            if ($end < count($this->jobs)) {
                $scrollIndicators .= $this->theme->dim(' ↓');
            }
            
            $output .= '  ' . $pageInfo . $countInfo . $scrollIndicators . "\n";
        }

        // Help line (compact for small terminals)
        if ($height >= 15) {
            if ($width >= 70) {
                $output .= '  ' . 
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Navigate  ') .
                       $this->theme->styled('PgUp/Dn', 'secondary') . $this->theme->dim(' Page  ') .
                       $this->theme->styled('Enter', 'secondary') . $this->theme->dim(' Details') . "\n";
            } else {
                $output .= '  ' . 
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim('Nav ') .
                       $this->theme->styled('PgUp/Dn', 'secondary') . $this->theme->dim('Pg') . "\n";
            }
        }

        return $output;
    }

    public function scrollUp(): void
    {
        if ($this->selectedIndex > 0) {
            $this->selectedIndex--;
            if ($this->selectedIndex < $this->scrollOffset) {
                $this->scrollOffset = $this->selectedIndex;
            }
        }
    }

    public function scrollDown(): void
    {
        if ($this->selectedIndex < count($this->jobs) - 1) {
            $this->selectedIndex++;
            $visibleCount = $this->getVisibleCount();
            if ($this->selectedIndex >= $this->scrollOffset + $visibleCount) {
                $this->scrollOffset = $this->selectedIndex - $visibleCount + 1;
            }
        }
    }

    public function pageUp(): void
    {
        $visibleCount = $this->getVisibleCount();
        $this->selectedIndex = max(0, $this->selectedIndex - $visibleCount);
        $this->scrollOffset = max(0, $this->scrollOffset - $visibleCount);
    }

    public function pageDown(): void
    {
        $visibleCount = $this->getVisibleCount();
        $maxIndex = max(0, count($this->jobs) - 1);
        $this->selectedIndex = min($maxIndex, $this->selectedIndex + $visibleCount);
        $this->scrollOffset = min($this->selectedIndex, $this->scrollOffset + $visibleCount);
    }

    public function scrollToTop(): void
    {
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;
    }

    public function scrollToBottom(): void
    {
        $visibleCount = $this->getVisibleCount();
        $this->selectedIndex = max(0, count($this->jobs) - 1);
        $this->scrollOffset = max(0, count($this->jobs) - $visibleCount);
    }
}