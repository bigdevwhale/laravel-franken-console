<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Adapters\LogAdapter;
use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Adapters\MetricsAdapter;
use Franken\Console\Support\Terminal;
use Franken\Console\Support\Theme;

class Dashboard
{
    private array $panels = [];
    private array $panelNames = [];
    private int $currentPanelIndex = 0;
    private string $currentPanel = 'overview';

    private QueueAdapter $queueAdapter;
    private LogAdapter $logAdapter;
    private CacheAdapter $cacheAdapter;
    private MetricsAdapter $metricsAdapter;
    private Terminal $terminal;
    private Theme $theme;

    public function __construct(
        QueueAdapter $queueAdapter,
        LogAdapter $logAdapter,
        CacheAdapter $cacheAdapter,
        MetricsAdapter $metricsAdapter,
        ?Terminal $terminal = null
    ) {
        $this->queueAdapter = $queueAdapter;
        $this->logAdapter = $logAdapter;
        $this->cacheAdapter = $cacheAdapter;
        $this->metricsAdapter = $metricsAdapter;
        $this->terminal = $terminal ?? new Terminal();
        $this->theme = new Theme();

        $this->panelNames = ['overview', 'queues', 'jobs', 'logs', 'cache', 'scheduler', 'metrics', 'shell', 'settings'];

        $this->panels = [
            'overview' => new OverviewPanel($this->terminal),
            'queues' => new QueuesPanel($this->queueAdapter),
            'jobs' => new JobsPanel($this->queueAdapter),
            'logs' => new LogsPanel($this->logAdapter),
            'cache' => new CacheConfigPanel($this->cacheAdapter),
            'scheduler' => new SchedulerPanel(),
            'metrics' => new MetricsPanel($this->metricsAdapter),
            'shell' => new ShellExecPanel(),
            'settings' => new SettingsPanel(),
        ];
    }

    public function render(): string
    {
        $width = $this->terminal->getWidth();
        $height = $this->terminal->getHeight();
        
        // Ensure sane minimum dimensions
        $width = max(40, min(500, $width));
        $height = max(10, min(200, $height));

        $lines = [];

        // Line 0: Tab bar (always first)
        $tabBar = $this->renderTabBar($width, $height);
        $lines[] = $tabBar;
        
        // Line 1: Separator (only if we have room)
        if ($height >= 10) {
            $lines[] = $this->theme->dim(str_repeat('─', $width));
        }

        // Get current panel and update its dimensions
        $panel = $this->panels[$this->currentPanel] ?? $this->panels['overview'];
        
        // Pass terminal dimensions to panel if it supports it
        if (method_exists($panel, 'setTerminalWidth')) {
            $panel->setTerminalWidth($width);
        }
        if (method_exists($panel, 'setTerminalHeight')) {
            $panel->setTerminalHeight($height);
        }
        
        // Render panel content and split into lines
        $panelContent = $panel->render();
        $panelLines = explode("\n", $panelContent);
        foreach ($panelLines as $line) {
            $lines[] = $line;
        }

        // Pad to fill screen height, leaving room for hotkey bar
        $hotkeyBarHeight = $height < 15 ? 0 : 2;
        $targetHeight = max(0, $height - $hotkeyBarHeight);
        
        while (count($lines) < $targetHeight) {
            $lines[] = '';
        }
        
        // Trim to target height to prevent overflow
        $lines = array_slice($lines, 0, $targetHeight);

        // Build output - clear each line to full width to prevent artifacts
        $output = '';
        foreach ($lines as $line) {
            // Calculate visible length (strip ANSI codes) for proper padding
            $visibleLen = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $line));
            $padding = max(0, $width - $visibleLen);
            $output .= $line . str_repeat(' ', $padding) . "\033[K\n";
        }

        // Render hotkey bar at bottom
        $output .= $this->renderHotkeyBar($width, $height);

        return $output;
    }

    private function renderTabBar(int $width, int $height = 24): string
    {
        // Determine if we need compact mode based on terminal width
        $isCompact = $width < 100;
        $isVeryCompact = $width < 70;
        $isTiny = $width < 50;
        
        // Tab labels adapt to available width
        $tabLabels = [
            'overview' => $isTiny ? '1' : ($isVeryCompact ? 'Ovw' : ($isCompact ? 'Over' : 'Overview')),
            'queues' => $isTiny ? '2' : ($isVeryCompact ? 'Que' : ($isCompact ? 'Ques' : 'Queues')),
            'jobs' => $isTiny ? '3' : ($isVeryCompact ? 'Job' : 'Jobs'),
            'logs' => $isTiny ? '4' : 'Logs',
            'cache' => $isTiny ? '5' : ($isVeryCompact ? 'Cch' : 'Cache'),
            'scheduler' => $isTiny ? '6' : ($isVeryCompact ? 'Sch' : ($isCompact ? 'Sched' : 'Scheduler')),
            'metrics' => $isTiny ? '7' : ($isVeryCompact ? 'Met' : ($isCompact ? 'Metr' : 'Metrics')),
            'shell' => $isTiny ? '8' : ($isVeryCompact ? 'Shl' : 'Shell'),
            'settings' => $isTiny ? '9' : ($isVeryCompact ? 'Set' : ($isCompact ? 'Sett' : 'Settings')),
        ];

        // Start output - always start at column 0
        $output = '';
        
        // App title/branding - adapt to width
        if ($width >= 80) {
            $output .= "\033[1;36m ⚡FRANKEN \033[0m"; // Bold cyan
            $output .= "\033[90m│\033[0m";
        } elseif ($width >= 50) {
            $output .= "\033[36m⚡\033[0m";
            $output .= "\033[90m│\033[0m";
        }
        // No branding for tiny terminals
        
        $tabNum = 1;
        foreach ($this->panelNames as $name) {
            $label = $tabLabels[$name] ?? ucfirst($name);
            $isActive = ($name === $this->currentPanel);
            
            if ($isActive) {
                // Active tab: inverse video with bold - very visible
                // Use raw ANSI for reliability: inverse + bold + white
                $output .= " \033[7;1;37m " . ($isTiny ? '' : $tabNum . ':') . $label . " \033[0m";
            } else {
                // Inactive tab: dim text
                if ($isTiny) {
                    $output .= " \033[90m" . $tabNum . "\033[0m";
                } else {
                    $output .= " \033[90m" . $tabNum . ':' . $label . "\033[0m";
                }
            }
            $tabNum++;
        }

        return $output;
    }

    private function renderHotkeyBar(int $width, int $height = 24): string
    {
        // Skip hotkey bar if terminal is very short
        if ($height < 15) {
            return '';
        }
        
        $output = $this->theme->styled(str_repeat('─', $width), 'muted') . "\n";
        
        // Build hotkey display - adapt to width
        $isCompact = $width < 80;
        $isTiny = $width < 50;
        
        if ($isTiny) {
            // Minimal hotkeys for tiny terminals
            $output .= $this->theme->styled('q', 'secondary') . $this->theme->dim('Quit ');
            $output .= $this->theme->styled('←→', 'secondary') . $this->theme->dim('Tab ');
            $output .= $this->theme->styled('↑↓', 'secondary') . $this->theme->dim('Nav');
            return $output;
        }
        
        $hotkeys = [
            ['q', $isCompact ? 'Quit' : 'Quit'],
            ['←→', $isCompact ? 'Tab' : 'Tabs'],
            ['↑↓', $isCompact ? 'Nav' : 'Scroll'],
            ['r', $isCompact ? 'Ref' : 'Refresh'],
        ];

        // Add context-specific hotkeys
        if ($this->currentPanel === 'cache') {
            $hotkeys[] = ['c', 'Clear'];
        }
        if ($this->currentPanel === 'queues') {
            $hotkeys[] = ['R', 'Restart'];
        }
        if ($this->currentPanel === 'logs') {
            $hotkeys[] = ['/', 'Search'];
        }

        $output .= ' ';
        foreach ($hotkeys as $hotkey) {
            $output .= $this->theme->styled(' ' . $hotkey[0] . ' ', 'secondary');
            $output .= $this->theme->dim($hotkey[1]) . ' ';
        }

        return $output;
    }

    public function switchPanel(string $panel): void
    {
        if (isset($this->panels[$panel])) {
            $this->currentPanel = $panel;
            $this->currentPanelIndex = array_search($panel, $this->panelNames, true);
            if ($this->currentPanelIndex === false) {
                $this->currentPanelIndex = 0;
            }
        }
    }

    public function nextPanel(): void
    {
        $this->currentPanelIndex = ($this->currentPanelIndex + 1) % count($this->panelNames);
        $this->currentPanel = $this->panelNames[$this->currentPanelIndex];
    }

    public function previousPanel(): void
    {
        $this->currentPanelIndex = ($this->currentPanelIndex - 1 + count($this->panelNames)) % count($this->panelNames);
        $this->currentPanel = $this->panelNames[$this->currentPanelIndex];
    }

    public function getCurrentPanel(): string
    {
        return $this->currentPanel;
    }

    public function navigateUp(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'scrollUp')) {
            $panel->scrollUp();
        }
    }

    public function navigateDown(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'scrollDown')) {
            $panel->scrollDown();
        }
    }

    public function navigateLeft(): void
    {
        $this->previousPanel();
    }

    public function navigateRight(): void
    {
        $this->nextPanel();
    }

    public function pageUp(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'pageUp')) {
            $panel->pageUp();
        }
    }

    public function pageDown(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'pageDown')) {
            $panel->pageDown();
        }
    }

    public function scrollToTop(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'scrollToTop')) {
            $panel->scrollToTop();
        }
    }

    public function scrollToBottom(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'scrollToBottom')) {
            $panel->scrollToBottom();
        }
    }

    public function enterSearchMode(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'enterSearchMode')) {
            $panel->enterSearchMode();
        }
    }

    public function exitSearchMode(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'exitSearchMode')) {
            $panel->exitSearchMode();
        }
    }

    public function isInSearchMode(): bool
    {
        $panel = $this->panels[$this->currentPanel];
        return method_exists($panel, 'isInSearchMode') && $panel->isInSearchMode();
    }

    public function addSearchChar(string $char): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'addSearchChar')) {
            $panel->addSearchChar($char);
        }
    }

    public function removeSearchChar(): void
    {
        $panel = $this->panels[$this->currentPanel];
        if (method_exists($panel, 'removeSearchChar')) {
            $panel->removeSearchChar();
        }
    }
}