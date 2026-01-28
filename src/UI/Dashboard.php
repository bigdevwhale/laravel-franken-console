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

        // Calculate available height for panel content
        // Reserve: 1 for tab bar, 1 for separator, 2 for hotkey bar
        $panelHeight = max(5, $height - 4);

        $lines = [];

        // Render header/tab bar
        $lines[] = $this->renderTabBar($width);
        
        // Render separator
        $lines[] = $this->theme->styled(str_repeat('─', $width), 'muted');

        // Get current panel and update its dimensions
        $panel = $this->panels[$this->currentPanel] ?? $this->panels['overview'];
        
        // Pass terminal dimensions to panel if it supports it
        if (method_exists($panel, 'setTerminalWidth')) {
            $panel->setTerminalWidth($width);
        }
        if (method_exists($panel, 'setTerminalHeight')) {
            $panel->setTerminalHeight($panelHeight);
        }
        
        // Render panel content and split into lines
        $panelContent = $panel->render();
        $panelLines = explode("\n", $panelContent);
        
        // IMPORTANT: Limit panel content to available height to prevent overflow
        $panelLines = array_slice($panelLines, 0, $panelHeight);
        
        foreach ($panelLines as $line) {
            $lines[] = $line;
        }

        // Pad to fill screen height, leaving room for hotkey bar
        $targetHeight = max(0, $height - 2); // Leave 2 lines for hotkey bar
        
        while (count($lines) < $targetHeight) {
            $lines[] = '';
        }
        
        // Trim to exact height if somehow over
        $lines = array_slice($lines, 0, $targetHeight);

        // Clear each line to full width to prevent artifacts
        $output = '';
        foreach ($lines as $line) {
            // Strip visible length for padding calculation
            $visibleLen = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $line));
            $padding = max(0, $width - $visibleLen);
            $output .= $line . str_repeat(' ', $padding) . "\033[K\n";
        }

        // Render hotkey bar at bottom
        $output .= $this->renderHotkeyBar($width);

        return $output;
    }

    private function renderTabBar(int $width): string
    {
        // Determine if we need compact mode based on terminal width
        $isCompact = $width < 100;
        $isVeryCompact = $width < 70;
        
        $tabLabels = [
            'overview' => $isVeryCompact ? 'Ovw' : ($isCompact ? 'Over' : 'Overview'),
            'queues' => $isVeryCompact ? 'Que' : ($isCompact ? 'Ques' : 'Queues'),
            'jobs' => $isVeryCompact ? 'Job' : 'Jobs',
            'logs' => 'Logs',
            'cache' => $isVeryCompact ? 'Cch' : 'Cache',
            'scheduler' => $isVeryCompact ? 'Sch' : ($isCompact ? 'Sched' : 'Scheduler'),
            'metrics' => $isVeryCompact ? 'Met' : ($isCompact ? 'Metr' : 'Metrics'),
            'shell' => $isVeryCompact ? 'Shl' : 'Shell',
            'settings' => $isVeryCompact ? 'Set' : ($isCompact ? 'Sett' : 'Settings'),
        ];

        $output = '';
        
        // App title/branding - compact on small terminals
        if ($width >= 80) {
            $output .= $this->theme->bold($this->theme->styled(' ⚡FRANKEN ', 'primary'));
        } else {
            $output .= $this->theme->styled(' ⚡ ', 'primary');
        }
        $output .= $this->theme->styled('│', 'muted');
        
        $tabNum = 1;
        foreach ($this->panelNames as $name) {
            $label = $tabLabels[$name] ?? ucfirst($name);
            $isActive = ($name === $this->currentPanel);
            
            if ($isActive) {
                // Active tab: inverse style - highly visible
                $output .= "\033[7m\033[1m " . $tabNum . ':' . $label . " \033[0m";
            } else {
                // Inactive tab: just number, dimmed
                $output .= $this->theme->dim(' ' . $tabNum . ':') . $this->theme->dim($label);
            }
            $tabNum++;
        }

        return $output;
    }

    private function renderHotkeyBar(int $width): string
    {
        $output = $this->theme->styled(str_repeat('─', $width), 'muted') . "\n";
        
        // Build hotkey display - adapt to width
        $isCompact = $width < 80;
        
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

    public function handleEnter(): void
    {
        $panel = $this->panels[$this->currentPanel];
        
        // Handle enter based on panel type
        if ($this->currentPanel === 'jobs' && method_exists($panel, 'viewDetails')) {
            $panel->viewDetails();
        } elseif ($this->currentPanel === 'logs' && method_exists($panel, 'exitSearchMode')) {
            // In logs, Enter confirms search
            if ($this->isInSearchMode()) {
                $panel->exitSearchMode();
            }
        }
        // Add more panel-specific Enter handling as needed
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