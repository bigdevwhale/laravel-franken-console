<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Theme;

class SchedulerPanel
{
    private Theme $theme;
    private int $selectedIndex = 0;
    private int $scrollOffset = 0;
    private array $scheduledTasks = [];
    private int $terminalHeight = 24;
    private int $terminalWidth = 80;

    public function __construct()
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

    private function getVisibleTaskCount(): int
    {
        // Reserve lines for headers, status section, help
        return max(2, $this->terminalHeight - 16);
    }

    public function render(): string
    {
        $width = $this->terminalWidth;
        $height = $this->terminalHeight;
        $lineWidth = max(40, $width - 4);
        
        $this->scheduledTasks = $this->getScheduledTasks();
        
        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('TASK SCHEDULER', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        // Status section (compact if height is limited)
        $isRunning = $this->isSchedulerRunning();
        $statusIcon = $isRunning ? 
            $this->theme->styled('● Running', 'success') : 
            $this->theme->styled('○ Not Running', 'warning');
        
        if ($height >= 20) {
            $lastRun = $this->getLastSchedulerRun();
            $nextRun = $this->getNextSchedulerRun();
            $output .= sprintf("  Status: %s  |  Last: %s  |  Next: %s\n",
                $statusIcon,
                $this->theme->styled($lastRun, 'info'),
                $this->theme->styled($nextRun, 'info')
            );
        } else {
            $output .= sprintf("  Status: %s\n", $statusIcon);
        }

        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
        $output .= '  ' . $this->theme->bold('Scheduled Tasks') . "\n";

        // Responsive header
        if ($width >= 90) {
            $output .= sprintf("  %-40s %-18s %-20s\n",
                $this->theme->dim('COMMAND'),
                $this->theme->dim('SCHEDULE'),
                $this->theme->dim('NEXT DUE')
            );
        } elseif ($width >= 60) {
            $output .= sprintf("  %-28s %-14s %-16s\n",
                $this->theme->dim('COMMAND'),
                $this->theme->dim('SCHEDULE'),
                $this->theme->dim('NEXT DUE')
            );
        } else {
            $output .= sprintf("  %-20s %-12s\n",
                $this->theme->dim('COMMAND'),
                $this->theme->dim('SCHEDULE')
            );
        }
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        if (empty($this->scheduledTasks)) {
            $output .= '  ' . $this->theme->dim('No scheduled tasks found') . "\n";
        } else {
            $visibleCount = $this->getVisibleTaskCount();
            $start = $this->scrollOffset;
            $end = min($start + $visibleCount, count($this->scheduledTasks));
            $totalPages = max(1, (int)ceil(count($this->scheduledTasks) / $visibleCount));
            $currentPage = (int)floor($this->scrollOffset / max(1, $visibleCount)) + 1;
            
            for ($i = $start; $i < $end; $i++) {
                $task = $this->scheduledTasks[$i];
                $isSelected = ($i === $this->selectedIndex);
                $marker = $isSelected ? $this->theme->styled('▸', 'primary') : ' ';
                
                $command = $task['command'];

                if ($width >= 90) {
                    if (strlen($command) > 38) {
                        $command = substr($command, 0, 35) . '...';
                    }
                    $cmdDisplay = $isSelected ? $this->theme->styled($command, 'primary') : $command;
                    $output .= sprintf("%s %-40s %-18s %-20s\n",
                        $marker,
                        $cmdDisplay,
                        $this->theme->dim($task['schedule']),
                        $this->theme->styled($task['next_due'], 'info')
                    );
                } elseif ($width >= 60) {
                    if (strlen($command) > 26) {
                        $command = substr($command, 0, 23) . '...';
                    }
                    $cmdDisplay = $isSelected ? $this->theme->styled($command, 'primary') : $command;
                    $output .= sprintf("%s %-28s %-14s %-16s\n",
                        $marker,
                        $cmdDisplay,
                        $this->theme->dim(substr($task['schedule'], 0, 12)),
                        $this->theme->styled(substr($task['next_due'], 0, 14), 'info')
                    );
                } else {
                    if (strlen($command) > 18) {
                        $command = substr($command, 0, 15) . '...';
                    }
                    $cmdDisplay = $isSelected ? $this->theme->styled($command, 'primary') : $command;
                    $output .= sprintf("%s %-20s %-12s\n",
                        $marker,
                        $cmdDisplay,
                        $this->theme->dim(substr($task['schedule'], 0, 10))
                    );
                }
            }
            
            // Page indicator if there are multiple pages
            if ($totalPages > 1) {
                $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
                $pageInfo = $this->theme->dim('Page ') . 
                           $this->theme->styled((string)$currentPage, 'info') . 
                           $this->theme->dim('/') . 
                           $this->theme->styled((string)$totalPages, 'info') .
                           $this->theme->dim(' (') .
                           $this->theme->styled((string)count($this->scheduledTasks), 'info') .
                           $this->theme->dim(' tasks)');
                $output .= '  ' . $pageInfo . "\n";
            }
        }

        // Help line (only if there's room)
        if ($height >= 15) {
            $output .= "\n";
            if ($width >= 60) {
                $output .= '  ' . $this->theme->dim('Use ') . 
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' to navigate, ') .
                       $this->theme->styled('Enter', 'secondary') . $this->theme->dim(' to run task') . "\n";
            } else {
                $output .= '  ' . $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Nav  ') .
                       $this->theme->styled('⏎', 'secondary') . $this->theme->dim(' Run') . "\n";
            }
        }

        return $output;
    }

    private function getScheduledTasks(): array
    {
        try {
            // Try to get scheduled tasks from Laravel's schedule
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            $events = $schedule->events();
            
            $tasks = [];
            foreach ($events as $event) {
                $tasks[] = [
                    'command' => $event->command ?? $event->description ?? 'Unknown',
                    'schedule' => $event->expression,
                    'next_due' => $this->getNextDueTime($event->expression),
                ];
            }
            
            return empty($tasks) ? $this->getMockTasks() : $tasks;
        } catch (\Exception $e) {
            return $this->getMockTasks();
        }
    }

    private function getMockTasks(): array
    {
        return [
            ['command' => 'inspire', 'schedule' => '* * * * *', 'next_due' => 'Every minute'],
            ['command' => 'backup:run', 'schedule' => '0 2 * * *', 'next_due' => 'Daily at 02:00'],
            ['command' => 'horizon:snapshot', 'schedule' => '*/5 * * * *', 'next_due' => 'Every 5 minutes'],
            ['command' => 'queue:prune-failed', 'schedule' => '0 0 * * *', 'next_due' => 'Daily at 00:00'],
            ['command' => 'telescope:prune', 'schedule' => '0 0 * * *', 'next_due' => 'Daily at 00:00'],
        ];
    }

    private function getNextDueTime(string $expression): string
    {
        try {
            $cron = new \Cron\CronExpression($expression);
            return $cron->getNextRunDate()->format('Y-m-d H:i');
        } catch (\Exception $e) {
            return $this->parseExpression($expression);
        }
    }

    private function parseExpression(string $expression): string
    {
        return match($expression) {
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '0 * * * *' => 'Every hour',
            '0 0 * * *' => 'Daily at 00:00',
            '0 2 * * *' => 'Daily at 02:00',
            default => $expression,
        };
    }

    private function isSchedulerRunning(): bool
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                return false; // Can't reliably detect on Windows
            }
            
            $output = shell_exec('pgrep -f "schedule:run" 2>/dev/null');
            return !empty($output);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getLastSchedulerRun(): string
    {
        try {
            $cacheFile = storage_path('framework/schedule-*');
            $files = glob($cacheFile);
            if (!empty($files)) {
                $lastModified = max(array_map('filemtime', $files));
                return date('Y-m-d H:i:s', $lastModified);
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return 'Unknown';
    }

    private function getNextSchedulerRun(): string
    {
        return date('Y-m-d H:i:s', strtotime('+1 minute'));
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
        if ($this->selectedIndex < count($this->scheduledTasks) - 1) {
            $this->selectedIndex++;
            $visibleCount = $this->getVisibleTaskCount();
            if ($this->selectedIndex >= $this->scrollOffset + $visibleCount) {
                $this->scrollOffset = $this->selectedIndex - $visibleCount + 1;
            }
        }
    }

    public function pageUp(): void
    {
        $visibleCount = $this->getVisibleTaskCount();
        $this->selectedIndex = max(0, $this->selectedIndex - $visibleCount);
        $this->scrollOffset = max(0, $this->scrollOffset - $visibleCount);
    }

    public function pageDown(): void
    {
        $visibleCount = $this->getVisibleTaskCount();
        $maxIndex = max(0, count($this->scheduledTasks) - 1);
        $this->selectedIndex = min($maxIndex, $this->selectedIndex + $visibleCount);
        $this->scrollOffset = min($this->selectedIndex, $this->scrollOffset + $visibleCount);
    }

    public function getSelectedTask(): ?array
    {
        return $this->scheduledTasks[$this->selectedIndex] ?? null;
    }
}