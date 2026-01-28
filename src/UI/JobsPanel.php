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

    public function __construct(private QueueAdapter $adapter)
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        // Get jobs from adapter
        $this->jobs = $this->adapter->getRecentJobs(50);
        
        $output = "\n";
        $output .= $this->theme->styled("  Recent Jobs\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        // Header
        $output .= sprintf(
            "  %-10s %-30s %-15s %-20s\n",
            $this->theme->bold('ID'),
            $this->theme->bold('Job Class'),
            $this->theme->bold('Status'),
            $this->theme->bold('Processed At')
        );
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        if (empty($this->jobs)) {
            $output .= $this->theme->dim("  No recent jobs found\n");
        } else {
            $visibleCount = 15;
            $start = $this->scrollOffset;
            $end = min($start + $visibleCount, count($this->jobs));

            for ($i = $start; $i < $end; $i++) {
                $job = $this->jobs[$i];
                $marker = ($i === $this->selectedIndex) ? $this->theme->styled('▸ ', 'primary') : '  ';
                
                $statusIcon = match($job['status']) {
                    'processed' => $this->theme->styled('✓ Completed', 'success'),
                    'failed' => $this->theme->styled('✗ Failed', 'error'),
                    'pending' => $this->theme->styled('◌ Pending', 'warning'),
                    'processing' => $this->theme->styled('◉ Running', 'info'),
                    default => $this->theme->dim($job['status']),
                };

                // Truncate job class name if too long
                $jobClass = $job['class'];
                if (strlen($jobClass) > 28) {
                    $jobClass = '...' . substr($jobClass, -25);
                }

                $output .= sprintf(
                    "%s%-10s %-30s %-15s %-20s\n",
                    $marker,
                    $job['id'],
                    $jobClass,
                    $statusIcon,
                    $job['processed_at']
                );
            }

            // Scroll indicators
            if ($this->scrollOffset > 0) {
                $output .= "\n" . $this->theme->dim("  ↑ More jobs above");
            }
            if ($end < count($this->jobs)) {
                $output .= "\n" . $this->theme->dim("  ↓ More jobs below");
            }
        }

        $output .= "\n\n";
        $output .= $this->theme->dim("  Use ↑/↓ to navigate, Enter to view details\n");

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
            $visibleCount = 15;
            if ($this->selectedIndex >= $this->scrollOffset + $visibleCount) {
                $this->scrollOffset = $this->selectedIndex - $visibleCount + 1;
            }
        }
    }

    public function pageUp(): void
    {
        $this->selectedIndex = max(0, $this->selectedIndex - 15);
        $this->scrollOffset = max(0, $this->scrollOffset - 15);
    }

    public function pageDown(): void
    {
        $maxIndex = max(0, count($this->jobs) - 1);
        $this->selectedIndex = min($maxIndex, $this->selectedIndex + 15);
        $this->scrollOffset = min($this->selectedIndex, $this->scrollOffset + 15);
    }

    public function scrollToTop(): void
    {
        $this->selectedIndex = 0;
        $this->scrollOffset = 0;
    }

    public function scrollToBottom(): void
    {
        $this->selectedIndex = max(0, count($this->jobs) - 1);
        $this->scrollOffset = max(0, count($this->jobs) - 15);
    }
}