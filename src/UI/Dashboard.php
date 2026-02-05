<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Illuminate\Support\Collection;
use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Adapters\LogAdapter;
use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Adapters\MetricsAdapter;
use Franken\Console\Support\Terminal;
use Franken\Console\Support\Theme;

class Dashboard
{
    /** @var Panel[] */
    private array $panels = [];
    private array $panelNames = [];
    private int $selectedPanelIndex = 0;

    private QueueAdapter $queueAdapter;
    private LogAdapter $logAdapter;
    private CacheAdapter $cacheAdapter;
    private MetricsAdapter $metricsAdapter;
    private Terminal $terminal;
    private Theme $theme;

    // Scrolling and search state (delegated to current panel)
    private bool $inSearchMode = false;
    private string $searchQuery = '';

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

        $this->initializePanels();
        $this->selectPanel(0); // Focus the first panel
    }

    private function initializePanels(): void
    {
        $this->panelNames = ['overview', 'queues', 'jobs', 'logs', 'cache', 'scheduler', 'metrics', 'shell', 'settings'];

        $this->panels = [
            new OverviewPanel('Overview', $this->terminal),
            new QueuesPanel('Queues', $this->queueAdapter),
            new JobsPanel('Jobs', $this->queueAdapter),
            new LogsPanel('Logs', $this->logAdapter),
            new CacheConfigPanel('Cache', $this->cacheAdapter),
            new SchedulerPanel('Scheduler'),
            new MetricsPanel('Metrics', $this->metricsAdapter),
            new ShellExecPanel('Shell'),
            new SettingsPanel('Settings'),
        ];

        // Set initial dimensions for all panels
        $width = $this->terminal->getWidth();
        $height = $this->terminal->getHeight();
        foreach ($this->panels as $panel) {
            $panel->setDimensions($width, $height);
        }
    }

    public function getCurrentPanel(): Panel
    {
        return $this->panels[$this->selectedPanelIndex];
    }

    public function selectPanel(int $index): void
    {
        // Blur the current panel
        if (isset($this->panels[$this->selectedPanelIndex])) {
            $this->panels[$this->selectedPanelIndex]->blur();
        }

        // Select and focus new panel
        $this->selectedPanelIndex = $index;
        $this->panels[$this->selectedPanelIndex]->focus();
    }

    public function switchPanel(string $panelName): void
    {
        $index = array_search($panelName, $this->panelNames);
        if ($index !== false) {
            $this->selectPanel($index);
        }
    }

    public function nextPanel(): void
    {
        $nextIndex = ($this->selectedPanelIndex + 1) % count($this->panels);
        $this->selectPanel($nextIndex);
    }

    public function previousPanel(): void
    {
        $nextIndex = ($this->selectedPanelIndex - 1 + count($this->panels)) % count($this->panels);
        $this->selectPanel($nextIndex);
    }

    public function render(): string
    {
        $width = $this->terminal->getWidth();
        $height = $this->terminal->getHeight();
        
        // Ensure sane minimum dimensions
        $width = max(60, min(300, $width));
        $height = max(15, min(100, $height));

        // Update panel dimensions
        foreach ($this->panels as $panel) {
            $panel->setDimensions($width, $height);
        }

        // Build output
        $output = '';

        // Line 0: Tab bar
        $output .= $this->renderTabBar($width) . "\033[K\n";
        
        // Line 1: Separator
        $output .= $this->theme->dim(str_repeat('─', $width)) . "\033[K\n";

        // Panel content (reserve 4 lines: tabs + separator + hotkeys + final line)
        $panelContent = $this->getCurrentPanel()->render();
        $panelLines = explode("\n", $panelContent);
        $availableLines = $height - 4;
        
        $lineCount = 0;
        foreach ($panelLines as $line) {
            if ($lineCount >= $availableLines) {
                break;
            }
            
            // Clean and truncate line
            $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
            if (mb_strlen($cleanLine) > $width) {
                $line = mb_substr($cleanLine, 0, $width - 3) . '...';
            }
            
            $output .= $line . "\033[K\n";
            $lineCount++;
        }

        // Fill remaining lines
        while ($lineCount < $availableLines) {
            $output .= "\033[K\n";
            $lineCount++;
        }

        // Hotkey bar
        $output .= $this->renderHotkeyBar($width) . "\033[K";

        return $output;
    }

    private function renderTabBar(int $width): string
    {
        $tabs = Collection::make($this->panels)->map(fn(Panel $panel, int $index) => [
            'panel' => $panel,
            'display' => ' ' . $panel->getName() . ' ',
            'focused' => $panel->isFocused(),
            'index' => $index
        ]);

        // Find the focused tab index
        $focusedIndex = $this->selectedPanelIndex;

        // Calculate visible tabs based on width (similar to Solo's algorithm)
        [$start, $end] = $this->calculateVisibleTabs($tabs, $focusedIndex, $width);

        $selectedTabs = $tabs
            ->slice($start, $end - $start + 1)
            ->map(fn($tab) => $this->styleTab($tab['panel'], $tab['display']))
            ->implode('');

        // Add overflow indicators if needed
        if ($start > 0) {
            $more = $this->theme->tabMore(" ← {$start} ");
            $selectedTabs = $more . $selectedTabs;
        }

        if ($end < $tabs->count() - 1) {
            $remaining = $tabs->count() - 1 - $end;
            $more = $this->theme->tabMore(" {$remaining} → ");
            $selectedTabs = $selectedTabs . $more;
        }

        return $selectedTabs;
    }

    private function calculateVisibleTabs($tabs, int $focused, int $maxWidth): array
    {
        // Start with just the focused tab
        $selectedTabs = Collection::make($tabs->slice($focused, 1));
        $left = $focused - 1;
        $right = $focused + 1;
        $totalTabs = $tabs->count();

        while (true) {
            $currentLength = mb_strlen(
                preg_replace('/\033\[[0-9;]*m/', '', 
                    $selectedTabs->pluck('display')->implode('')
                )
            );

            if ($currentLength >= $maxWidth - 12) { // Reserve space for overflow indicators
                break;
            }

            $canAddLeft = false;
            $canAddRight = false;

            // Check if we can add tab to the left
            if ($left >= 0) {
                $leftLength = mb_strlen($tabs[$left]['display']);
                if ($currentLength + $leftLength <= $maxWidth - 12) {
                    $canAddLeft = true;
                }
            }

            // Check if we can add tab to the right
            if ($right < $totalTabs) {
                $rightLength = mb_strlen($tabs[$right]['display']);
                if ($currentLength + $rightLength <= $maxWidth - 12) {
                    $canAddRight = true;
                }
            }

            if (!$canAddLeft && !$canAddRight) {
                break;
            }

            // Prefer balanced distribution around focused tab
            if ($canAddLeft && $canAddRight) {
                $canAddLeft = ($focused - $left) <= ($right - $focused);
                $canAddRight = !$canAddLeft;
            }

            if ($canAddLeft) {
                $selectedTabs->prepend($tabs[$left]);
                $left--;
            }

            if ($canAddRight) {
                $selectedTabs->push($tabs[$right]);
                $right++;
            }
        }

        return [++$left, --$right];
    }

    private function styleTab(Panel $panel, string $name): string
    {
        $state = $panel->getStatus();
        return $panel->isFocused() 
            ? $this->theme->tabFocused($name, $state) 
            : $this->theme->tabBlurred($name, $state);
    }

    private function renderHotkeyBar(int $width): string
    {
        $hotkeys = [
            'q' => 'Quit',
            'r' => 'Refresh',
            '←/→' => 'Tabs',
            '↑/↓' => 'Navigate',
            '/' => 'Search',
            'c' => 'Clear Cache',
        ];

        $hotkeyString = '';
        foreach ($hotkeys as $key => $label) {
            $hotkeyString .= $this->theme->hotkey($key) . ' ' . $this->theme->hotkeyLabel($label) . '  ';
        }

        // Center the hotkey bar
        $cleanLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $hotkeyString));
        if ($cleanLength < $width) {
            $padding = ($width - $cleanLength) / 2;
            $hotkeyString = str_repeat(' ', (int) floor($padding)) . $hotkeyString;
        }

        return $hotkeyString;
    }

    // Navigation methods (delegate to current panel)
    public function navigateUp(): void
    {
        $this->getCurrentPanel()->navigateUp();
    }

    public function navigateDown(): void
    {
        $this->getCurrentPanel()->navigateDown();
    }

    public function pageUp(): void
    {
        $this->getCurrentPanel()->pageUp();
    }

    public function pageDown(): void
    {
        $this->getCurrentPanel()->pageDown();
    }

    public function scrollToTop(): void
    {
        $this->getCurrentPanel()->scrollToTop();
    }

    public function scrollToBottom(): void
    {
        $this->getCurrentPanel()->scrollToBottom();
    }

    // Search methods
    public function enterSearchMode(): void
    {
        $this->inSearchMode = true;
        $this->searchQuery = '';
        $this->getCurrentPanel()->enterSearchMode();
    }

    public function exitSearchMode(): void
    {
        $this->inSearchMode = false;
        $this->searchQuery = '';
        $this->getCurrentPanel()->exitSearchMode();
    }

    public function addSearchChar(string $char): void
    {
        $this->searchQuery .= $char;
        $this->getCurrentPanel()->addSearchChar($char);
    }

    public function removeSearchChar(): void
    {
        if (strlen($this->searchQuery) > 0) {
            $this->searchQuery = substr($this->searchQuery, 0, -1);
            $this->getCurrentPanel()->removeSearchChar();
        }
    }

    public function isInSearchMode(): bool
    {
        return $this->inSearchMode || $this->getCurrentPanel()->isInSearchMode();
    }
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
}