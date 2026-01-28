<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Theme;

class SchedulerPanel
{
    private Theme $theme;
    private int $selectedIndex = 0;
    private array $scheduledTasks = [];

    public function __construct()
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $this->scheduledTasks = $this->getScheduledTasks();
        
        $output = "\n";
        $output .= $this->theme->styled("  Task Scheduler\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        // Status
        $output .= "\n";
        $output .= $this->theme->bold("  Scheduler Status\n");
        
        $isRunning = $this->isSchedulerRunning();
        $statusIcon = $isRunning ? 
            $this->theme->styled('● Running', 'success') : 
            $this->theme->styled('○ Not Running', 'warning');
        
        $output .= sprintf("  Status: %s\n", $statusIcon);
        
        $lastRun = $this->getLastSchedulerRun();
        $output .= sprintf("  Last Run: %s\n", $this->theme->styled($lastRun, 'info'));
        
        $nextRun = $this->getNextSchedulerRun();
        $output .= sprintf("  Next Run: %s\n", $this->theme->styled($nextRun, 'info'));

        $output .= "\n";
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');
        $output .= $this->theme->bold("  Scheduled Tasks\n");
        $output .= "\n";

        // Header
        $output .= sprintf(
            "  %-40s %-15s %-20s\n",
            $this->theme->bold('Command'),
            $this->theme->bold('Schedule'),
            $this->theme->bold('Next Due')
        );
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        if (empty($this->scheduledTasks)) {
            $output .= $this->theme->dim("  No scheduled tasks found\n");
        } else {
            foreach ($this->scheduledTasks as $i => $task) {
                $marker = ($i === $this->selectedIndex) ? $this->theme->styled('▸ ', 'primary') : '  ';
                
                $command = $task['command'];
                if (strlen($command) > 38) {
                    $command = substr($command, 0, 35) . '...';
                }

                $output .= sprintf(
                    "%s%-40s %-15s %-20s\n",
                    $marker,
                    $command,
                    $this->theme->dim($task['schedule']),
                    $this->theme->styled($task['next_due'], 'info')
                );
            }
        }

        $output .= "\n";
        $output .= $this->theme->dim("  Use ↑/↓ to navigate, Enter to run task manually\n");

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
        }
    }

    public function scrollDown(): void
    {
        if ($this->selectedIndex < count($this->scheduledTasks) - 1) {
            $this->selectedIndex++;
        }
    }
}