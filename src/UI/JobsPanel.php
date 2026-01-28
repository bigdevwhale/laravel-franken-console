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
    private bool $showingDetails = false;

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

    public function render(): string
    {
        // Get jobs from adapter
        $this->jobs = $this->adapter->getRecentJobs(50);
        $lineWidth = min(76, $this->terminalWidth - 4);
        
        // Calculate visible job count based on terminal height
        $visibleCount = max(5, $this->terminalHeight - 8);
        
        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('JOBS', 'secondary'));
        $output .= '  ' . $this->theme->dim('(' . count($this->jobs) . ' total)') . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        if (empty($this->jobs)) {
            $output .= '  ' . $this->theme->dim('No recent jobs found') . "\n";
        } else {
            $start = $this->scrollOffset;
            $end = min($start + $visibleCount, count($this->jobs));

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

                // Truncate job class name based on width
                $maxClassLen = max(15, $this->terminalWidth - 40);
                $jobClass = $job['class'];
                if (strlen($jobClass) > $maxClassLen) {
                    $jobClass = '…' . substr($jobClass, -($maxClassLen - 1));
                }

                $line = sprintf(
                    "  %s %s %-6s %s  %s",
                    $marker,
                    $statusIcon,
                    $job['id'],
                    $isSelected ? $this->theme->styled($jobClass, 'primary') : $jobClass,
                    $this->theme->dim($job['processed_at'])
                );
                
                $output .= $line . "\n";
            }

            // Scroll status
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            $output .= '  ' . $this->theme->dim('Showing ') . 
                       $this->theme->styled((string)($end - $start), 'info') .
                       $this->theme->dim('/') .
                       $this->theme->styled((string)count($this->jobs), 'info');
            
            if ($start > 0) {
                $output .= ' ' . $this->theme->dim('↑');
            }
            if ($end < count($this->jobs)) {
                $output .= ' ' . $this->theme->dim('↓');
            }
            $output .= "\n";
        }

        $output .= "\n";
        $output .= '  ' . $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Navigate  ') .
                   $this->theme->styled('Enter', 'secondary') . $this->theme->dim(' Details  ') .
                   $this->theme->styled('r', 'secondary') . $this->theme->dim(' Retry') . "\n";

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
            $visibleCount = max(5, $this->terminalHeight - 8);
            if ($this->selectedIndex >= $this->scrollOffset + $visibleCount) {
                $this->scrollOffset = $this->selectedIndex - $visibleCount + 1;
            }
        }
    }

    public function pageUp(): void
    {
        $visibleCount = max(5, $this->terminalHeight - 8);
        $this->selectedIndex = max(0, $this->selectedIndex - $visibleCount);
        $this->scrollOffset = max(0, $this->scrollOffset - $visibleCount);
    }

    public function pageDown(): void
    {
        $visibleCount = max(5, $this->terminalHeight - 8);
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
        $visibleCount = max(5, $this->terminalHeight - 8);
        $this->selectedIndex = max(0, count($this->jobs) - 1);
        $this->scrollOffset = max(0, count($this->jobs) - $visibleCount);
    }

    public function getSelectedJob(): ?array
    {
        return $this->jobs[$this->selectedIndex] ?? null;
    }

    public function viewDetails(): void
    {
        $this->showingDetails = true;
    }

    public function closeDetails(): void
    {
        $this->showingDetails = false;
    }

    public function isShowingDetails(): bool
    {
        return $this->showingDetails;
    }
}