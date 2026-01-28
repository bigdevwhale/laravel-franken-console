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
        $output .= '  ' . $this->theme->bold($this->theme->styled('QUEUE STATUS', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', 70)) . "\n\n";
        
        // Header row with proper alignment
        $output .= '  ' . $this->theme->dim(sprintf(
            "  %-18s %12s %12s %10s   %-15s",
            'QUEUE', 'PENDING', 'FAILED', 'WORKERS', 'STATUS'
        )) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', 70)) . "\n";

        if (empty($this->queues)) {
            $output .= "\n" . '  ' . $this->theme->dim('  No queues configured') . "\n";
        } else {
            foreach ($this->queues as $i => $queue) {
                $isSelected = ($i === $this->selectedIndex);
                $marker = $isSelected ? $this->theme->styled(' ▸', 'primary') : '  ';
                
                $failedColor = $queue['failed'] > 0 ? 'error' : 'success';
                $sizeColor = $queue['size'] > 100 ? 'warning' : ($queue['size'] > 0 ? 'info' : 'muted');
                
                // Status with icon
                if ($queue['size'] > 0) {
                    $status = $this->theme->styled('● ', 'success') . $this->theme->styled('Processing', 'success');
                } else {
                    $status = $this->theme->dim('○ Idle');
                }

                $queueName = $isSelected 
                    ? $this->theme->styled($queue['name'], 'primary')
                    : $queue['name'];

                $output .= sprintf(
                    "%s %-18s %12s %12s %10s   %s\n",
                    $marker,
                    $queueName,
                    $this->theme->styled(str_pad((string)$queue['size'], 4, ' ', STR_PAD_LEFT), $sizeColor),
                    $this->theme->styled(str_pad((string)$queue['failed'], 4, ' ', STR_PAD_LEFT), $failedColor),
                    $this->theme->dim('1'),
                    $status
                );
            }
        }

        $output .= "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('WORKERS', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', 70)) . "\n\n";

        if (empty($stats['workers'])) {
            $output .= '  ' . $this->theme->dim('  No workers running') . "\n";
        } else {
            foreach ($stats['workers'] as $worker) {
                $statusIcon = $worker['status'] === 'running' ? '●' : '○';
                $statusColor = $worker['status'] === 'running' ? 'success' : 'error';
                $output .= sprintf(
                    "    %s PID %s  %s\n",
                    $this->theme->styled($statusIcon, $statusColor),
                    $this->theme->styled((string)$worker['pid'], 'info'),
                    $this->theme->styled(ucfirst($worker['status']), $statusColor)
                );
            }
        }

        $output .= "\n";
        $output .= '  ' . $this->theme->dim('  ') . 
                   $this->theme->styled('R', 'secondary') . $this->theme->dim(' Restart worker  ') .
                   $this->theme->styled('r', 'secondary') . $this->theme->dim(' Retry failed') . "\n";

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