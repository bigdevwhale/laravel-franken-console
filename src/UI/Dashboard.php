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

        // Update panel dimensions - content area is smaller due to borders
        $contentWidth = $width - 4;  // Account for borders and padding
        $contentHeight = $height - 6; // Reserve: tabs(1) + status(1) + top border(1) + bottom border(1) + hotkeys(1) + buffer(1)
        foreach ($this->panels as $panel) {
            $panel->setDimensions($contentWidth, $contentHeight);
        }

        // Build output
        $output = '';

        // Line 0: Tab bar
        $output .= $this->renderTabBar($width) . "\n";

        // Line 1: Process status line
        $output .= $this->renderProcessState($width) . "\n";

        // Content pane with borders like Solo
        $output .= $this->renderContentPane($width, $height);

        // Hotkey bar
        $output .= $this->renderHotkeyBar($width);

        return $output;
    }

    private function renderTabBar(int $width): string
    {
        $width = $this->terminal->getWidth();
        $tabs = [];
        $visibleTabs = [];
        $overflowLeft = 0;
        $overflowRight = 0;
        $tabWidth = max(10, (int)($width / count($this->panels)));  // Dynamic tab width like Solo

        foreach ($this->panels as $index => $panel) {
            $name = $panel->getName();
            $state = $panel->getStatus();
            $isSelected = ($index === $this->selectedPanelIndex);

            $tabText = str_pad($name, $tabWidth - 2, ' ', STR_PAD_BOTH);  // Center text in tab
            if (mb_strlen($tabText) > $tabWidth) {
                $tabText = mb_substr($tabText, 0, $tabWidth - 3) . '...';  // Truncate long tabs
            }

            if ($isSelected) {
                $tab = $this->theme->tabFocused($tabText, $state);
            } else {
                $tab = $this->theme->tabBlurred($tabText, $state);
            }
            $tabs[] = $tab;
        }

        // Handle overflow (show arrows like "← 2" if tabs don't fit, similar to Solo's navigation)
        $totalTabWidth = array_sum(array_map('mb_strlen', $tabs)) + (count($tabs) - 1) * 3;  // Account for separators
        if ($totalTabWidth > $width) {
            // Implement visible window of tabs (e.g., show 5 centered on selected)
            $visibleCount = max(3, (int)($width / $tabWidth));
            $start = max(0, $this->selectedPanelIndex - (int)($visibleCount / 2));
            $end = min(count($tabs), $start + $visibleCount);
            $visibleTabs = array_slice($tabs, $start, $visibleCount);

            $overflowLeft = $start;
            $overflowRight = count($tabs) - $end;
        } else {
            $visibleTabs = $tabs;
        }

        $tabString = implode($this->theme->dim(' │ '), $visibleTabs);  // Visible separator like Solo

        // Add overflow indicators
        if ($overflowLeft > 0) {
            $tabString = $this->theme->tabMore("← $overflowLeft") . ' │ ' . $tabString;
        }
        if ($overflowRight > 0) {
            $tabString .= ' │ ' . $this->theme->tabMore("$overflowRight →");
        }

        // Pad to full width
        $cleanLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $tabString));
        if ($cleanLength < $width) {
            $tabString .= str_repeat(' ', $width - $cleanLength);
        }

        return $tabString . "\n" . $this->theme->dim(str_repeat('─', $width)) . "\n";
    }

    private function styleTab(Panel $panel, string $display): string
    {
        if ($panel->isFocused()) {
            return $this->theme->tabFocused($display, 'focused');
        } else {
            return $this->theme->tabBlurred($display, '');
        }
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

    private function renderProcessState(int $width): string
    {
        $currentPanel = $this->getCurrentPanel();
        $panelName = $currentPanel->getName();
        $status = $currentPanel->getStatus();
        
        $statusText = match($status) {
            'running', 'focused' => $this->theme->processRunning('Running:'),
            'stopped' => $this->theme->processStopped('Stopped:'),
            'paused' => $this->theme->logsPaused('Paused:'),
            default => $this->theme->dim('Status:'),
        };
        
        $panelInfo = $this->theme->styled($panelName, 'secondary');
        
        // Center the status line
        $statusLine = $statusText . ' ' . $panelInfo;
        $statusLen = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $statusLine));
        $padding = max(0, $width - $statusLen);
        $leftPad = (int)floor($padding / 2);
        
        return str_repeat(' ', $leftPad) . $statusLine . str_repeat(' ', $padding - $leftPad);
    }

    private function renderContentPane(int $width, int $height): string
    {
        $contentWidth = $width - 4;
        $availableLines = $height - 6; // Total available for content pane
        
        $panelContent = $this->getCurrentPanel()->render();
        $contentLines = explode("\n", trim($panelContent));
        
        // Render box top
        $border = $this->theme->boxBorder('╭') . 
                  str_repeat($this->theme->boxBorder('─'), $width - 2) . 
                  $this->theme->boxBorder('╮');
        
        $output = $border . "\n";
        
        // Add viewing line
        $totalLines = count($contentLines);
        $start = 1;
        $end = min($totalLines, $availableLines - 3); // Account for borders and viewing line
        
        $count = "Viewing [$start-$end] of $totalLines";
        $state = $this->getCurrentPanel()->isPaused() ? '(Paused)' : '(Live)';
        $stateTreatment = $this->getCurrentPanel()->isPaused() ? 'logsPaused' : 'logsLive';
        
        $viewingLine = $this->theme->dim($count) . ' ' . $this->theme->{$stateTreatment}($state);
        $viewingPadded = $this->theme->boxBorder('│') . ' ' . $viewingLine . str_repeat(' ', $contentWidth - mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $viewingLine))) . ' ' . $this->theme->boxBorder('│');
        $output .= $viewingPadded . "\n";
        
        // Separator
        $output .= $this->theme->boxBorder('├') . str_repeat($this->theme->boxBorder('─'), $width - 2) . $this->theme->boxBorder('┤') . "\n";
        
        // Content lines
        $lineCount = 0;
        foreach ($contentLines as $line) {
            if ($lineCount >= $availableLines - 3) { // Account for top border, viewing, separator, bottom border
                break;
            }
            
            $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
            $lineLen = mb_strlen($cleanLine);
            
            if ($lineLen > $contentWidth) {
                $line = mb_substr($cleanLine, 0, $contentWidth - 3) . '...';
                $lineLen = $contentWidth;
            }
            
            $padding = max(0, $contentWidth - $lineLen);
            $output .= $this->theme->boxBorder('│') . ' ' . $line . str_repeat(' ', $padding) . ' ' . $this->theme->boxBorder('│') . "\n";
            $lineCount++;
        }
        
        // Fill remaining lines
        while ($lineCount < $availableLines - 3) {
            $output .= $this->theme->boxBorder('│') . str_repeat(' ', $contentWidth + 2) . $this->theme->boxBorder('│') . "\n";
            $lineCount++;
        }
        
        // Box bottom border
        $output .= $this->theme->boxBorder('╰') . str_repeat($this->theme->boxBorder('─'), $width - 2) . $this->theme->boxBorder('╯') . "\n";
        
        return $output;
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

        // Truncate if too long
        $cleanLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $hotkeyString));
        if ($cleanLength > $width) {
            $hotkeyString = mb_substr($hotkeyString, 0, $width - 3) . '...';
        }

        // Center the hotkey bar
        $finalLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $hotkeyString));
        if ($finalLength < $width) {
            $padding = ($width - $finalLength) / 2;
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