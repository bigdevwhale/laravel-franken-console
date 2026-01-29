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
        
        // Ensure sane minimum dimensions - be generous for SSH sessions
        $width = max(60, min(300, $width));
        $height = max(15, min(100, $height));

        // Build output starting with position reset
        $output = '';

        // Line 0: Tab bar (always first, always visible)
        $tabBar = $this->renderTabBar($width, $height);
        $tabBarVisible = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $tabBar));
        $output .= $tabBar . str_repeat(' ', max(0, $width - $tabBarVisible)) . "\033[K\n";
        
        // Line 1: Separator
        $output .= $this->theme->dim(str_repeat('─', $width)) . "\033[K\n";

        // Get current panel and update its dimensions
        $panel = $this->panels[$this->currentPanel] ?? $this->panels['overview'];
        
        // Calculate available content height (total height minus tab bar, separator, and hotkey bar)
        $hotkeyBarHeight = ($height >= 15) ? 2 : 0; // Hotkey bar takes 2 lines if shown
        $availableContentHeight = $height - 2 - $hotkeyBarHeight; // 2 for tabs + separator
        
        // Pass terminal dimensions to panel if it supports it
        if (method_exists($panel, 'setTerminalWidth')) {
            $panel->setTerminalWidth($width);
        }
        if (method_exists($panel, 'setTerminalHeight')) {
            $panel->setTerminalHeight($availableContentHeight);
        }
        
        // Render panel content
        $panelContent = $panel->render();
        $panelLines = explode("\n", $panelContent);
        
        // Calculate how many lines we have for panel content
        // Reserve: 2 for tab bar + separator, and conditionally for hotkey bar
        $hotkeyBarHeight = ($height >= 15) ? 2 : 0; // Hotkey bar takes 2 lines if shown
        $maxContentLines = 20; // Limit content to reasonable amount to avoid scrolling
        $availableLines = min($height - 2 - $hotkeyBarHeight, $maxContentLines);
        $lineCount = 0;
        
        foreach ($panelLines as $line) {
            if ($lineCount >= $availableLines) {
                break;
            }
            $visibleLen = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $line));
            $padding = max(0, $width - $visibleLen);
            $output .= $line . str_repeat(' ', $padding) . "\033[K\n";
            $lineCount++;
        }

        // Pad remaining space
        while ($lineCount < $availableLines) {
            $output .= str_repeat(' ', $width) . "\033[K\n";
            $lineCount++;
        }

        // Render hotkey bar at bottom
        $output .= $this->renderHotkeyBar($width, $height);

        return $output;
    }

    private function renderTabBar(int $width, int $height = 24): string
    {
        $output = '';
        
        // Simple tab display: show current panel and navigation
        $currentIndex = array_search($this->currentPanel, $this->panelNames, true);
        $totalPanels = count($this->panelNames);
        
        // Show branding if space
        $branding = '';
        if ($width >= 80) {
            $branding = ' FRANKEN ';
            if (mb_strlen($branding) + 10 > $width) {
                $branding = ' FK ';
            }
        }
        
        $output .= $branding;
        
        // Show current panel
        $currentName = ucfirst($this->currentPanel);
        if (mb_strlen($currentName) > 10) {
            $currentName = substr($currentName, 0, 7) . '...';
        }
        
        $tabInfo = '[' . ($currentIndex + 1) . '/' . $totalPanels . ': ' . $currentName . ']';
        $output .= $tabInfo;
        
        // Add navigation hints if space
        $remainingSpace = $width - mb_strlen($output);
        if ($remainingSpace > 10) {
            if ($currentIndex > 0) {
                $output .= ' ←';
            }
            if ($currentIndex < $totalPanels - 1) {
                $output .= ' →';
            }
        }
        
        // Pad to full width
        $output .= str_repeat(' ', max(0, $width - mb_strlen($output)));
        
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