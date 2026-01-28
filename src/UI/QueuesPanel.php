<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Support\Theme;

class QueuesPanel
{
    private Theme $theme;
    private int $selectedIndex = 0;
    private array $queues = [];

    public function __construct(private QueueAdapter $adapter)
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $stats = $this->adapter->getQueueStats();
        $this->queues = $stats['queues'];
        
        $output = "\n";
        $output .= $this->theme->styled("  Queue Status\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────\n", 'muted');
        
        // Header
        $output .= sprintf(
            "  %-20s %10s %10s %10s %s\n",
            $this->theme->bold('Queue'),
            $this->theme->bold('Pending'),
            $this->theme->bold('Failed'),
            $this->theme->bold('Workers'),
            $this->theme->bold('Status')
        );
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────\n", 'muted');

        if (empty($this->queues)) {
            $output .= $this->theme->dim("  No queues found\n");
        } else {
            foreach ($this->queues as $i => $queue) {
                $marker = ($i === $this->selectedIndex) ? $this->theme->styled('▸ ', 'primary') : '  ';
                
                $failedColor = $queue['failed'] > 0 ? 'error' : 'success';
                $sizeColor = $queue['size'] > 100 ? 'warning' : 'foreground';
                
                $status = $queue['size'] > 0 ? 
                    $this->theme->styled('● Processing', 'success') : 
                    $this->theme->dim('○ Idle');

                $output .= sprintf(
                    "%s%-20s %10s %10s %10s %s\n",
                    $marker,
                    $queue['name'],
                    $this->theme->styled((string)$queue['size'], $sizeColor),
                    $this->theme->styled((string)$queue['failed'], $failedColor),
                    '1',
                    $status
                );
            }
        }

        $output .= "\n";
        $output .= $this->theme->styled("  Workers\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────\n", 'muted');

        foreach ($stats['workers'] as $worker) {
            $statusColor = $worker['status'] === 'running' ? 'success' : 'error';
            $output .= sprintf(
                "  PID %-8s %s\n",
                $worker['pid'],
                $this->theme->styled($worker['status'], $statusColor)
            );
        }

        $output .= "\n";
        $output .= $this->theme->dim("  Press R to restart queue worker, r to retry failed jobs\n");

        return $output;
    }

    public function scrollUp(): void
    {
        if ($this->selectedIndex > 0) {
            $this->selectedIndex--;
        }
    }

    public function scrollDown(): void
    {
        if ($this->selectedIndex < count($this->queues) - 1) {
            $this->selectedIndex++;
        }
    }

    public function getSelectedQueue(): ?array
    {
        return $this->queues[$this->selectedIndex] ?? null;
    }
}