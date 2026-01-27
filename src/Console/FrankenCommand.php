<?php

declare(strict_types=1);

namespace Franken\Console\Console;

use Illuminate\Console\Command;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use Franken\Console\UI\Dashboard;
use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Adapters\LogAdapter;
use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Adapters\MetricsAdapter;

class FrankenCommand extends Command
{
    protected $signature = 'franken';
    protected $description = 'Launch Franken-Console TUI dashboard';

    private Dashboard $dashboard;
    private QueueAdapter $queueAdapter;
    private LogAdapter $logAdapter;
    private CacheAdapter $cacheAdapter;
    private MetricsAdapter $metricsAdapter;
    private float $lastPollTime = 0;

    private function shouldPoll(): bool
    {
        $interval = config('franken.polling_interval', 2);
        return (microtime(true) - $this->lastPollTime) >= $interval;
    }

    private function pollData(): void
    {
        $this->lastPollTime = microtime(true);
        // Data is polled on-demand in adapters
    }

    public function __construct()
    {
        parent::__construct();
        $this->queueAdapter = new QueueAdapter();
        $this->logAdapter = new LogAdapter();
        $this->cacheAdapter = new CacheAdapter();
        $this->metricsAdapter = new MetricsAdapter();
        $this->dashboard = new Dashboard(
            $this->queueAdapter,
            $this->logAdapter,
            $this->cacheAdapter,
            $this->metricsAdapter
        );
        $this->keybindings = config('franken.keybindings', []);
    }

    public function handle(): int
    {
        $this->info('Starting Franken-Console... Press q to quit.');

        // Set terminal to raw mode for key input
        system('stty raw -echo');

        $this->render();

        // Main loop using stream_select for better input handling
        while (true) {
            $read = [STDIN];
            $write = null;
            $except = null;

            // Poll for input with timeout
            $result = @stream_select($read, $write, $except, 0, 100000); // 100ms timeout

            if ($result === 1) {
                $key = fread(STDIN, 10);
                if ($this->handleKey($key)) {
                    break; // Quit
                }
            }

            // Check if it's time to poll data
            if ($this->shouldPoll()) {
                $this->pollData();
                $this->render();
            }
        }

        // Restore terminal
        system('stty sane');

        return 0;
    }

    private function handleKey(string $key): bool
    {
        // Handle escape sequences for special keys
        if (str_starts_with($key, "\033")) {
            return $this->handleEscapeSequence($key);
        }

        // Single character keys
        switch ($key) {
            case $this->keybindings['quit']:
                return true; // Quit
            case $this->keybindings['refresh']:
                $this->render();
                break;
            case $this->keybindings['switch_overview']:
                $this->dashboard->switchPanel('overview');
                $this->render();
                break;
            case $this->keybindings['switch_queues']:
                $this->dashboard->switchPanel('queues');
                $this->render();
                break;
            case $this->keybindings['switch_jobs']:
                $this->dashboard->switchPanel('jobs');
                $this->render();
                break;
            case $this->keybindings['switch_logs']:
                $this->dashboard->switchPanel('logs');
                $this->render();
                break;
            case $this->keybindings['switch_cache']:
                $this->dashboard->switchPanel('cache');
                $this->render();
                break;
            case $this->keybindings['switch_scheduler']:
                $this->dashboard->switchPanel('scheduler');
                $this->render();
                break;
            case $this->keybindings['switch_metrics']:
                $this->dashboard->switchPanel('metrics');
                $this->render();
                break;
            case $this->keybindings['switch_shell']:
                $this->dashboard->switchPanel('shell');
                $this->render();
                break;
            case $this->keybindings['switch_settings']:
                $this->dashboard->switchPanel('settings');
                $this->render();
                break;
            case $this->keybindings['clear_cache']:
                $this->cacheAdapter->clearCache();
                $this->info('Cache cleared');
                break;
            case $this->keybindings['restart_worker']:
                $this->queueAdapter->restartWorker();
                $this->info('Worker restart attempted');
                break;
            case $this->keybindings['search_logs']:
                $this->dashboard->enterSearchMode();
                $this->render();
                break;
            case $this->keybindings['navigate_down']:
                $this->dashboard->navigateDown();
                $this->render();
                break;
            case $this->keybindings['navigate_up']:
                $this->dashboard->navigateUp();
                $this->render();
                break;
            default:
                // Handle search input if in search mode
                if ($this->dashboard->isInSearchMode()) {
                    $this->handleSearchInput($key);
                    $this->render();
                }
                break;
        }

        return false; // Continue
    }

    private function handleEscapeSequence(string $key): bool
    {
        // Arrow keys and other escape sequences
        switch ($key) {
            case "\033[A": // Up arrow
                $this->dashboard->navigateUp();
                $this->render();
                break;
            case "\033[B": // Down arrow
                $this->dashboard->navigateDown();
                $this->render();
                break;
            case "\033[D": // Left arrow
                $this->dashboard->navigateLeft();
                $this->render();
                break;
            case "\033[C": // Right arrow
                $this->dashboard->navigateRight();
                $this->render();
                break;
            case "\033[5~": // Page Up
                $this->dashboard->pageUp();
                $this->render();
                break;
            case "\033[6~": // Page Down
                $this->dashboard->pageDown();
                $this->render();
                break;
            case "\033[H": // Home
                $this->dashboard->scrollToTop();
                $this->render();
                break;
            case "\033[F": // End
                $this->dashboard->scrollToBottom();
                $this->render();
                break;
            case "\177": // Backspace (escape sequence)
            case "\b":   // Backspace (direct)
                if ($this->dashboard->isInSearchMode()) {
                    $this->dashboard->removeSearchChar();
                    $this->render();
                }
                break;
        }

        return false;
    }

    private function handleSearchInput(string $key): void
    {
        if ($key === "\n" || $key === "\r") { // Enter
            // Exit search mode
            $this->dashboard->exitSearchMode();
        } elseif ($key === "\033") { // Escape
            $this->dashboard->exitSearchMode();
        } elseif (ctype_print($key)) {
            $this->dashboard->addSearchChar($key);
        }
    }

    private function render(): void
    {
        // Clear screen and render
        echo "\033[2J\033[H"; // Clear screen and move to top
        echo $this->dashboard->render();
    }
}