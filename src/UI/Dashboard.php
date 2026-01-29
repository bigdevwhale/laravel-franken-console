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
        $availableLines = $height - 2 - $hotkeyBarHeight;
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
        // Determine if we need compact mode based on terminal width
        $isUltraWide = $width >= 200;
        $isWide = $width >= 150;
        $isCompact = $width < 100;
        $isVeryCompact = $width < 70;
        $isTiny = $width < 50;
        
        // Tab labels adapt to available width - prioritize brevity
        $tabLabels = [
            'overview' => $isTiny ? '1' : ($isVeryCompact ? 'O' : ($isCompact ? 'Ov' : ($isWide ? 'Ovr' : ($isUltraWide ? 'Overview' : 'Overvw')))),
            'queues' => $isTiny ? '2' : ($isVeryCompact ? 'Q' : ($isCompact ? 'Qu' : ($isWide ? 'Que' : ($isUltraWide ? 'Queues' : 'Queue')))),
            'jobs' => $isTiny ? '3' : ($isVeryCompact ? 'J' : ($isWide ? 'Job' : 'Jobs')),
            'logs' => $isTiny ? '4' : ($isVeryCompact ? 'L' : 'Logs'),
            'cache' => $isTiny ? '5' : ($isVeryCompact ? 'C' : ($isCompact ? 'Ca' : ($isWide ? 'Cac' : 'Cache'))),
            'scheduler' => $isTiny ? '6' : ($isVeryCompact ? 'S' : ($isCompact ? 'Sc' : ($isWide ? 'Sch' : ($isUltraWide ? 'Scheduler' : 'Sched')))),
            'metrics' => $isTiny ? '7' : ($isVeryCompact ? 'M' : ($isCompact ? 'Me' : ($isWide ? 'Met' : 'Metr'))),
            'shell' => $isTiny ? '8' : ($isVeryCompact ? 'H' : ($isWide ? 'Shl' : 'Shell')),
            'settings' => $isTiny ? '9' : ($isVeryCompact ? 'T' : ($isCompact ? 'Se' : ($isWide ? 'Set' : 'Sett'))),
        ];

        // Start output - always start at column 0
        $output = '';
        
        // App title/branding - adapt to width
        if ($width >= 200) {
            $output .= "\033[1;36m ⚡ FRANKEN CONSOLE \033[0m"; // Full branding for ultra-wide
            $output .= "\033[90m│\033[0m";
        } elseif ($width >= 120) {
            $output .= "\033[1;36m ⚡FRANKEN \033[0m"; // Bold cyan
            $output .= "\033[90m│\033[0m";
        } elseif ($width >= 80) {
            $output .= "\033[36m⚡\033[0m";
            $output .= "\033[90m│\033[0m";
        }
        // No branding for narrow terminals
        
        $tabNum = 1;
        $currentLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $output));
        
        // First pass: ensure active tab is included
        $activeTabText = '';
        $activeTabLength = 0;
        foreach ($this->panelNames as $name) {
            $label = $tabLabels[$name] ?? ucfirst($name);
            $isActive = ($name === $this->currentPanel);
            
            if ($isActive) {
                $activeTabText = " \033[1;96m" . ($isTiny ? '' : $tabNum . ':') . $label . "\033[0m ";
                $activeTabLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $activeTabText));
                break;
            }
            $tabNum++;
        }
        
        // Add active tab first if it fits
        $activeTabAdded = false;
        if ($activeTabText) {
            // Ensure active tab fits, truncate branding if necessary
            if ($currentLength + $activeTabLength > $width - 2) {
                // Remove branding to make space for active tab
                $brandingLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $output));
                $output = '';
                $currentLength = 0;
            }
            
            if ($activeTabLength <= $width - 2) {
                $output .= $activeTabText;
                $currentLength += $activeTabLength;
                $activeTabAdded = true;
            } else {
                // Active tab too long even without branding, show minimal version
                $minimalActive = " \033[1;96m" . $tabNum . "\033[0m ";
                $minimalLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $minimalActive));
                if ($minimalLength <= $width - 2) {
                    $output .= $minimalActive;
                    $currentLength += $minimalLength;
                    $activeTabAdded = true;
                }
            }
        }
        
        // Second pass: add other tabs that fit
        $tabNum = 1;
        $tabsShown = 0;
        $totalTabs = count($this->panelNames);
        $firstVisibleTab = -1;
        $lastVisibleTab = -1;
        
        foreach ($this->panelNames as $index => $name) {
            $label = $tabLabels[$name] ?? ucfirst($name);
            $isActive = ($name === $this->currentPanel);
            
            // Skip active tab (already added)
            if ($isActive) {
                $tabsShown++;
                $tabNum++;
                continue;
            }
            
            $tabText = '';
            if ($isTiny) {
                $tabText = " \033[90m" . $tabNum . "\033[0m";
            } else {
                $tabText = " \033[90m" . $tabNum . ':' . $label . "\033[0m";
            }
            
            $tabVisibleLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $tabText));
            
            // Check if adding this tab would exceed width
            if ($currentLength + $tabVisibleLength > $width - 2) {
                // Add navigation indicators
                if ($width >= 60) {
                    // Show left arrow if there are tabs before the first visible
                    if ($firstVisibleTab > 0) {
                        $leftArrow = " \033[90m‹\033[0m";
                        $arrowLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $leftArrow));
                        if ($currentLength + $arrowLength <= $width - 2) {
                            $output = $leftArrow . $output; // Prepend
                            $currentLength += $arrowLength;
                        }
                    }
                    
                    // Show right arrow if there are tabs after the last visible
                    if ($lastVisibleTab < $totalTabs - 1) {
                        $rightArrow = " \033[90m›\033[0m";
                        $arrowLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $rightArrow));
                        if ($currentLength + $arrowLength <= $width - 2) {
                            $output .= $rightArrow;
                        }
                    }
                }
                break; // Stop adding tabs if we'd exceed width
            }
            
            if ($firstVisibleTab === -1) {
                $firstVisibleTab = $index;
            }
            $lastVisibleTab = $index;
            
            $output .= $tabText;
            $currentLength += $tabVisibleLength;
            $tabsShown++;
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