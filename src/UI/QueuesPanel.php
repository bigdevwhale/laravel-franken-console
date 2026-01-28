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

    public function render(): string
    {
        $stats = $this->adapter->getQueueStats();
        $this->queues = $stats['queues'];
        $lineWidth = min(70, $this->terminalWidth - 4);
        
        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('QUEUES', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        if (empty($this->queues)) {
            $output .= '  ' . $this->theme->dim('No queues configured') . "\n";
        } else {
            // Limit queues to available space
            $maxQueues = max(3, (int)(($this->terminalHeight - 12) / 2));
            $displayQueues = array_slice($this->queues, 0, $maxQueues);
            
            foreach ($displayQueues as $i => $queue) {
                $isSelected = ($i === $this->selectedIndex);
                $marker = $isSelected ? $this->theme->styled('▸', 'primary') : ' ';
                
                $failedColor = $queue['failed'] > 0 ? 'error' : 'success';
                $sizeColor = $queue['size'] > 100 ? 'warning' : ($queue['size'] > 0 ? 'info' : 'muted');
                
                $status = $queue['size'] > 0 
                    ? $this->theme->styled('●', 'success')
                    : $this->theme->dim('○');

                $queueName = $isSelected 
                    ? $this->theme->styled($queue['name'], 'primary')
                    : $queue['name'];

                $output .= sprintf(
                    "  %s %s %-15s %s%s %s%s\n",
                    $marker,
                    $status,
                    $queueName,
                    $this->theme->dim('pending:'),
                    $this->theme->styled((string)$queue['size'], $sizeColor),
                    $this->theme->dim('failed:'),
                    $this->theme->styled((string)$queue['failed'], $failedColor)
                );
            }
            
            if (count($this->queues) > $maxQueues) {
                $output .= '  ' . $this->theme->dim('  +' . (count($this->queues) - $maxQueues) . ' more...') . "\n";
            }
        }

        $output .= "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('WORKERS', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        if (empty($stats['workers'])) {
            $output .= '  ' . $this->theme->dim('No workers running') . "\n";
        } else {
            // Limit workers display
            $maxWorkers = max(3, $this->terminalHeight - 15 - count($this->queues));
            $displayWorkers = array_slice($stats['workers'], 0, $maxWorkers);
            
            foreach ($displayWorkers as $worker) {
                $statusIcon = $worker['status'] === 'running' ? '●' : '○';
                $statusColor = $worker['status'] === 'running' ? 'success' : 'error';
                $output .= sprintf(
                    "  %s PID %s %s\n",
                    $this->theme->styled($statusIcon, $statusColor),
                    $this->theme->styled((string)$worker['pid'], 'info'),
                    $this->theme->styled(ucfirst($worker['status']), $statusColor)
                );
            }
            
            if (count($stats['workers']) > $maxWorkers) {
                $output .= '  ' . $this->theme->dim('+' . (count($stats['workers']) - $maxWorkers) . ' more workers...') . "\n";
            }
        }

        $output .= "\n";
        $output .= '  ' . $this->theme->styled('R', 'secondary') . $this->theme->dim(' Restart  ') .
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