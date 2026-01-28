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

        $output = '';

        // Render header/tab bar
        $output .= $this->renderTabBar($width) . "\n";
        
        // Render separator
        $output .= $this->theme->styled(str_repeat('─', $width), 'muted') . "\n";

        // Get current panel
        $panel = $this->panels[$this->currentPanel] ?? $this->panels['overview'];
        
        // Render panel content
        $output .= $panel->render();

        // Add some padding
        $output .= "\n";

        // Render hotkey bar at bottom
        $output .= $this->renderHotkeyBar($width);

        return $output;
    }

    private function renderTabBar(int $width): string
    {
        $tabs = [];
        $tabLabels = [
            'overview' => '1:Overview',
            'queues' => '2:Queues',
            'jobs' => '3:Jobs',
            'logs' => '4:Logs',
            'cache' => '5:Cache',
            'scheduler' => '6:Scheduler',
            'metrics' => '7:Metrics',
            'shell' => '8:Shell',
            'settings' => '9:Settings',
        ];

        foreach ($this->panelNames as $name) {
            $label = $tabLabels[$name] ?? ucfirst($name);
            $isActive = ($name === $this->currentPanel);
            
            if ($isActive) {
                $tabs[] = $this->theme->styled(" {$label} ", 'primary') . $this->theme->bold('');
            } else {
                $tabs[] = $this->theme->dim(" {$label} ");
            }
        }

        return implode($this->theme->styled('│', 'muted'), $tabs);
    }

    private function renderHotkeyBar(int $width): string
    {
        $hotkeys = [
            'q' => 'Quit',
            '←/→' => 'Switch tabs',
            '↑/↓' => 'Navigate',
            'r' => 'Refresh',
            '/' => 'Search',
        ];

        // Add context-specific hotkeys
        if ($this->currentPanel === 'cache') {
            $hotkeys['c'] = 'Clear cache';
        }
        if ($this->currentPanel === 'queues') {
            $hotkeys['R'] = 'Restart worker';
        }

        $hotkeyStr = '';
        foreach ($hotkeys as $key => $desc) {
            $hotkeyStr .= $this->theme->styled($key, 'secondary') . ' ' . $this->theme->dim($desc) . '  ';
        }

        return $this->theme->styled(str_repeat('─', $width), 'muted') . "\n" . $hotkeyStr;
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