<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Support\Theme;

class QueuesPanel extends Panel
{
    private Theme $theme;
    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private array $queues = [];

    public function __construct(string $name = 'Queues', ?QueueAdapter $adapter = null)
    {
        parent::__construct($name);
        $this->theme = new Theme();
        $this->adapter = $adapter;
    }

    private ?QueueAdapter $adapter;

    private function getVisibleQueueCount(): int
    {
        // Reserve lines for headers, workers section, help
        return max(2, (int)(($this->getHeight() - 15) / 2));
    }

    public function render(): string
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        $lineWidth = max(40, $width - 4);
        
        $stats = $this->adapter->getQueueStats();
        $this->queues = $stats['queues'];
        
        $output = $this->theme->bold($this->theme->styled('QUEUE STATUS', 'secondary')) . "\n";
        $output .= $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        
        // Responsive header
        if ($width >= 90) {
            $output .= $this->theme->dim(sprintf(
                "%-18s %10s %10s %8s   %-12s",
                'QUEUE', 'PENDING', 'FAILED', 'WORKERS', 'STATUS'
            )) . "\n";
        } elseif ($width >= 60) {
            $output .= $this->theme->dim(sprintf(
                "%-14s %8s %8s %-10s",
                'QUEUE', 'PEND', 'FAIL', 'STATUS'
            )) . "\n";
        } else {
            $output .= $this->theme->dim(sprintf(
                "%-10s %6s %6s",
                'QUEUE', 'PEND', 'FAIL'
            )) . "\n";
        }
        $output .= $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        if (empty($this->queues)) {
            $output .= $this->theme->dim('No queues configured') . "\n";
        } else {
            $visibleCount = $this->getVisibleQueueCount();
            $start = $this->scrollOffset;
            $end = min($start + $visibleCount, count($this->queues));
            $totalPages = max(1, (int)ceil(count($this->queues) / $visibleCount));
            $currentPage = (int)floor($this->scrollOffset / max(1, $visibleCount)) + 1;
            
            for ($i = $start; $i < $end; $i++) {
                $queue = $this->queues[$i];
                $isSelected = ($i === $this->selectedIndex);
                $marker = $isSelected ? $this->theme->styled('▸', 'primary') : ' ';
                
                $failedColor = $queue['failed'] > 0 ? 'error' : 'success';
                $sizeColor = $queue['size'] > 100 ? 'warning' : ($queue['size'] > 0 ? 'info' : 'muted');
                
                // Status with icon
                $statusIcon = $queue['size'] > 0 ? $this->theme->styled('●', 'success') : $this->theme->dim('○');
                $statusText = $queue['size'] > 0 ? $this->theme->styled('Active', 'success') : $this->theme->dim('Idle');

                $queueName = $isSelected 
                    ? $this->theme->styled($queue['name'], 'primary')
                    : $queue['name'];

                if ($width >= 90) {
                    $output .= sprintf(
                        "%s %-18s %10s %10s %8s   %s %s\n",
                        $marker,
                        $queueName,
                        $this->theme->styled((string)$queue['size'], $sizeColor),
                        $this->theme->styled((string)$queue['failed'], $failedColor),
                        $this->theme->dim('1'),
                        $statusIcon,
                        $statusText
                    );
                } elseif ($width >= 60) {
                    $output .= sprintf(
                        "%s %-14s %8s %8s %s %s\n",
                        $marker,
                        $queueName,
                        $this->theme->styled((string)$queue['size'], $sizeColor),
                        $this->theme->styled((string)$queue['failed'], $failedColor),
                        $statusIcon,
                        $statusText
                    );
                } else {
                    $output .= sprintf(
                        "%s %-10s %6s %6s\n",
                        $marker,
                        substr($queueName, 0, 10),
                        $this->theme->styled((string)$queue['size'], $sizeColor),
                        $this->theme->styled((string)$queue['failed'], $failedColor)
                    );
                }
            }
            
            // Page indicator if there are multiple pages
            if ($totalPages > 1) {
                $output .= $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
                $pageInfo = $this->theme->dim('Page ') . 
                           $this->theme->styled((string)$currentPage, 'info') . 
                           $this->theme->dim('/') . 
                           $this->theme->styled((string)$totalPages, 'info') .
                           $this->theme->dim(' (') .
                           $this->theme->styled((string)count($this->queues), 'info') .
                           $this->theme->dim(' queues)');
                $output .= $pageInfo . "\n";
            }
        }

        // Workers section (only if there's room)
        if ($height >= 18) {
            $output .= "\n";
            $output .= '  ' . $this->theme->bold($this->theme->styled('WORKERS', 'secondary')) . "\n";
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

            if (empty($stats['workers'])) {
                $output .= '  ' . $this->theme->dim('  No workers running') . "\n";
            } else {
                $maxWorkers = min(count($stats['workers']), max(2, $height - 20));
                for ($w = 0; $w < $maxWorkers; $w++) {
                    $worker = $stats['workers'][$w];
                    $statusIcon = $worker['status'] === 'running' ? '●' : '○';
                    $statusColor = $worker['status'] === 'running' ? 'success' : 'error';
                    $output .= sprintf(
                        "    %s PID %s %s\n",
                        $this->theme->styled($statusIcon, $statusColor),
                        $this->theme->styled((string)$worker['pid'], 'info'),
                        $this->theme->styled(ucfirst($worker['status']), $statusColor)
                    );
                }
                if (count($stats['workers']) > $maxWorkers) {
                    $output .= '    ' . $this->theme->dim('... and ' . (count($stats['workers']) - $maxWorkers) . ' more') . "\n";
                }
            }
        }

        // Help line (only if there's room)
        if ($height >= 15) {
            $output .= "\n";
            if ($width >= 60) {
                $output .= '  ' . 
                       $this->theme->styled('R', 'secondary') . $this->theme->dim(' Restart  ') .
                       $this->theme->styled('r', 'secondary') . $this->theme->dim(' Retry failed  ') .
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Select') . "\n";
            } else {
                $output .= '  ' . 
                       $this->theme->styled('R', 'secondary') . $this->theme->dim('Rst ') .
                       $this->theme->styled('r', 'secondary') . $this->theme->dim('Retry') . "\n";
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
        if ($this->selectedIndex < count($this->queues) - 1) {
            $this->selectedIndex++;
            $visibleCount = $this->getVisibleQueueCount();
            if ($this->selectedIndex >= $this->scrollOffset + $visibleCount) {
                $this->scrollOffset = $this->selectedIndex - $visibleCount + 1;
            }
        }
    }

    public function pageUp(): void
    {
        $visibleCount = $this->getVisibleQueueCount();
        $this->selectedIndex = max(0, $this->selectedIndex - $visibleCount);
        $this->scrollOffset = max(0, $this->scrollOffset - $visibleCount);
    }

    public function pageDown(): void
    {
        $visibleCount = $this->getVisibleQueueCount();
        $maxIndex = max(0, count($this->queues) - 1);
        $this->selectedIndex = min($maxIndex, $this->selectedIndex + $visibleCount);
        $this->scrollOffset = min($this->selectedIndex, $this->scrollOffset + $visibleCount);
    }

    public function getSelectedQueue(): ?array
    {
        return $this->queues[$this->selectedIndex] ?? null;
    }
}