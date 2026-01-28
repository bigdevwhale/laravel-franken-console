<?php

declare(strict_types=1);

namespace Franken\Console\Console;

use Illuminate\Console\Command;
use Franken\Console\UI\Dashboard;
use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Adapters\LogAdapter;
use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Adapters\MetricsAdapter;
use Franken\Console\Support\Terminal;

class FrankenCommand extends Command
{
    protected $signature = 'franken';
    protected $description = 'Launch Franken-Console TUI dashboard';

    private Dashboard $dashboard;
    private QueueAdapter $queueAdapter;
    private LogAdapter $logAdapter;
    private CacheAdapter $cacheAdapter;
    private MetricsAdapter $metricsAdapter;
    private array $keybindings = [];
    private float $lastPollTime = 0;
    private Terminal $terminal;
    private bool $running = true;

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
    }

    protected function setup(): void
    {
        $this->keybindings = config('franken.keybindings', [
            'quit' => 'q',
            'refresh' => 'r',
            'restart_worker' => 'R',
            'clear_cache' => 'c',
            'search_logs' => '/',
            'navigate_down' => 'j',
            'navigate_up' => 'k',
            'switch_overview' => '1',
            'switch_queues' => '2',
            'switch_jobs' => '3',
            'switch_logs' => '4',
            'switch_cache' => '5',
            'switch_scheduler' => '6',
            'switch_metrics' => '7',
            'switch_shell' => '8',
            'switch_settings' => '9',
        ]);

        $this->queueAdapter = new QueueAdapter();
        $this->logAdapter = new LogAdapter();
        $this->cacheAdapter = new CacheAdapter();
        $this->metricsAdapter = new MetricsAdapter();
        $this->terminal = new Terminal();
        $this->dashboard = new Dashboard(
            $this->queueAdapter,
            $this->logAdapter,
            $this->cacheAdapter,
            $this->metricsAdapter,
            $this->terminal
        );
    }

    public function handle(): int
    {
        $this->setup();
        
        $this->info('Starting Franken-Console... Press q to quit.');

        // Set terminal to raw mode for key input (cross-platform)
        $this->terminal->enableRawMode();

        // Enter alternate screen buffer
        $this->terminal->enterAlternateScreen();

        // Hide cursor
        $this->terminal->hideCursor();

        $this->render();

        // Main loop using stream_select for better input handling
        while ($this->running) {
            $read = [STDIN];
            $write = null;
            $except = null;

            // Poll for input with timeout (25ms = ~40 FPS like Solo)
            $result = @stream_select($read, $write, $except, 0, 25000);

            if ($result === 1) {
                $key = fread(STDIN, 10);
                if ($key !== false && $key !== '') {
                    if ($this->handleKey($key)) {
                        break; // Quit
                    }
                }
            }

            // Check if it's time to poll data
            if ($this->shouldPoll()) {
                $this->pollData();
                $this->render();
            }
        }

        // Restore terminal
        $this->terminal->showCursor();
        $this->terminal->exitAlternateScreen();
        $this->terminal->disableRawMode();

        return 0;
    }

    private function handleKey(string $key): bool
    {
        // Handle escape sequences for special keys
        if (str_starts_with($key, "\033")) {
            return $this->handleEscapeSequence($key);
        }

        // Handle Ctrl+C
        if ($key === "\x03") {
            return true; // Quit
        }

        // Get keybinding or use default empty string
        $quitKey = $this->keybindings['quit'] ?? 'q';
        $refreshKey = $this->keybindings['refresh'] ?? 'r';
        $clearCacheKey = $this->keybindings['clear_cache'] ?? 'c';
        $restartWorkerKey = $this->keybindings['restart_worker'] ?? 'R';
        $searchLogsKey = $this->keybindings['search_logs'] ?? '/';
        $navDownKey = $this->keybindings['navigate_down'] ?? 'j';
        $navUpKey = $this->keybindings['navigate_up'] ?? 'k';

        // Single character keys
        switch ($key) {
            case $quitKey:
                return true; // Quit
            case $refreshKey:
                $this->render();
                break;
            case '1':
                $this->dashboard->switchPanel('overview');
                $this->render();
                break;
            case '2':
                $this->dashboard->switchPanel('queues');
                $this->render();
                break;
            case '3':
                $this->dashboard->switchPanel('jobs');
                $this->render();
                break;
            case '4':
                $this->dashboard->switchPanel('logs');
                $this->render();
                break;
            case '5':
                $this->dashboard->switchPanel('cache');
                $this->render();
                break;
            case '6':
                $this->dashboard->switchPanel('scheduler');
                $this->render();
                break;
            case '7':
                $this->dashboard->switchPanel('metrics');
                $this->render();
                break;
            case '8':
                $this->dashboard->switchPanel('shell');
                $this->render();
                break;
            case '9':
                $this->dashboard->switchPanel('settings');
                $this->render();
                break;
            case $clearCacheKey:
                $this->cacheAdapter->clearCache();
                $this->render();
                break;
            case $restartWorkerKey:
                $this->queueAdapter->restartWorker();
                $this->render();
                break;
            case $searchLogsKey:
                $this->dashboard->enterSearchMode();
                $this->render();
                break;
            case $navDownKey:
                $this->dashboard->navigateDown();
                $this->render();
                break;
            case $navUpKey:
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
            case "\033[D": // Left arrow - previous tab
                $this->dashboard->previousPanel();
                $this->render();
                break;
            case "\033[C": // Right arrow - next tab
                $this->dashboard->nextPanel();
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
            case "\033[1~": // Home (alternate)
                $this->dashboard->scrollToTop();
                $this->render();
                break;
            case "\033[F": // End
            case "\033[4~": // End (alternate)
                $this->dashboard->scrollToBottom();
                $this->render();
                break;
            case "\177": // Backspace (escape sequence)
            case "\b":   // Backspace (direct)
            case "\x7f": // Delete
                if ($this->dashboard->isInSearchMode()) {
                    $this->dashboard->removeSearchChar();
                    $this->render();
                }
                break;
            case "\033": // Escape key alone
                if ($this->dashboard->isInSearchMode()) {
                    $this->dashboard->exitSearchMode();
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
        // Force terminal to re-read dimensions on each render
        // This handles resize events properly
        $this->terminal->refreshDimensions();
        
        // Reset all terminal state and position
        echo "\033[?25l";   // Hide cursor
        echo "\033[H";      // Move cursor to home (1,1)
        echo "\033[2J";     // Clear entire screen
        echo "\033[H";      // Move cursor to home again (ensure position)
        
        $output = $this->dashboard->render();
        echo $output;
        
        // Flush output buffer
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }
}