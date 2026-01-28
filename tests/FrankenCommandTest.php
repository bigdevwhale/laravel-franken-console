<?php

declare(strict_types=1);

namespace Franken\Console\Tests;

use Orchestra\Testbench\TestCase;
use Franken\Console\FrankenServiceProvider;
use Franken\Console\Adapters\QueueAdapter;
use Franken\Console\Adapters\LogAdapter;
use Franken\Console\Adapters\CacheAdapter;
use Franken\Console\Adapters\MetricsAdapter;
use Franken\Console\UI\Dashboard;
use Franken\Console\UI\OverviewPanel;
use Franken\Console\UI\QueuesPanel;
use Franken\Console\UI\JobsPanel;
use Franken\Console\UI\LogsPanel;
use Franken\Console\UI\CacheConfigPanel;
use Franken\Console\UI\SchedulerPanel;
use Franken\Console\UI\MetricsPanel;
use Franken\Console\UI\ShellExecPanel;
use Franken\Console\UI\SettingsPanel;
use Franken\Console\Support\Terminal;
use Franken\Console\Support\Theme;

class FrankenCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [FrankenServiceProvider::class];
    }

    public function testCommandIsRegistered(): void
    {
        $this->assertTrue($this->artisan('franken', ['--help'])->run() === 0);
    }

    public function testAdaptersCanBeInstantiated(): void
    {
        $queueAdapter = new QueueAdapter();
        $this->assertIsArray($queueAdapter->getQueueStats());
        $this->assertArrayHasKey('queues', $queueAdapter->getQueueStats());
        $this->assertArrayHasKey('workers', $queueAdapter->getQueueStats());

        $logAdapter = new LogAdapter();
        $this->assertIsArray($logAdapter->getRecentLogs());

        $cacheAdapter = new CacheAdapter();
        $stats = $cacheAdapter->getCacheStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('driver', $stats);
        $this->assertArrayHasKey('size', $stats);

        $metricsAdapter = new MetricsAdapter();
        $metrics = $metricsAdapter->getMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('memory', $metrics);
        $this->assertArrayHasKey('requests', $metrics);
    }

    public function testPanelsRender(): void
    {
        $queueAdapter = new QueueAdapter();
        $logAdapter = new LogAdapter();
        $cacheAdapter = new CacheAdapter();
        $metricsAdapter = new MetricsAdapter();
        $terminal = new Terminal();

        $overview = new OverviewPanel($terminal);
        $this->assertIsString($overview->render());

        $queues = new QueuesPanel($queueAdapter);
        $this->assertIsString($queues->render());

        $jobs = new JobsPanel($queueAdapter);
        $this->assertIsString($jobs->render());

        $logs = new LogsPanel($logAdapter);
        $this->assertIsString($logs->render());

        $cache = new CacheConfigPanel($cacheAdapter);
        $this->assertIsString($cache->render());

        $scheduler = new SchedulerPanel();
        $this->assertIsString($scheduler->render());

        $metrics = new MetricsPanel($metricsAdapter);
        $this->assertIsString($metrics->render());

        $shell = new ShellExecPanel();
        $this->assertIsString($shell->render());

        $settings = new SettingsPanel();
        $this->assertIsString($settings->render());
    }

    public function testDashboardPanelSwitching(): void
    {
        $queueAdapter = new QueueAdapter();
        $logAdapter = new LogAdapter();
        $cacheAdapter = new CacheAdapter();
        $metricsAdapter = new MetricsAdapter();
        $terminal = new Terminal();

        $dashboard = new Dashboard(
            $queueAdapter,
            $logAdapter,
            $cacheAdapter,
            $metricsAdapter,
            $terminal
        );

        // Test initial panel
        $this->assertEquals('overview', $dashboard->getCurrentPanel());

        // Test switching panels
        $dashboard->switchPanel('logs');
        $this->assertEquals('logs', $dashboard->getCurrentPanel());

        $dashboard->switchPanel('queues');
        $this->assertEquals('queues', $dashboard->getCurrentPanel());

        // Test next/previous
        $dashboard->switchPanel('overview');
        $dashboard->nextPanel();
        $this->assertEquals('queues', $dashboard->getCurrentPanel());

        $dashboard->previousPanel();
        $this->assertEquals('overview', $dashboard->getCurrentPanel());

        // Test wrap-around
        $dashboard->previousPanel();
        $this->assertEquals('settings', $dashboard->getCurrentPanel());
    }

    public function testTheme(): void
    {
        $theme = new Theme();
        
        // Test styled text
        $styled = $theme->styled('Test', 'primary');
        $this->assertIsString($styled);
        $this->assertStringContainsString('Test', $styled);
        
        // Test dim
        $dim = $theme->dim('Dim text');
        $this->assertStringContainsString('Dim text', $dim);
        
        // Test bold
        $bold = $theme->bold('Bold text');
        $this->assertStringContainsString('Bold text', $bold);
    }

    public function testTerminal(): void
    {
        $terminal = new Terminal();
        
        $this->assertIsInt($terminal->getWidth());
        $this->assertIsInt($terminal->getHeight());
        $this->assertIsBool($terminal->isWindows());
        
        $dimensions = $terminal->getDimensions();
        $this->assertIsArray($dimensions);
        $this->assertCount(2, $dimensions);
    }

    public function testQueueAdapterMethods(): void
    {
        $adapter = new QueueAdapter();
        
        $recentJobs = $adapter->getRecentJobs(10);
        $this->assertIsArray($recentJobs);
        
        $stats = $adapter->getQueueStats();
        $this->assertArrayHasKey('queues', $stats);
        $this->assertArrayHasKey('workers', $stats);
    }

    public function testLogAdapterMethods(): void
    {
        $adapter = new LogAdapter();
        
        $logs = $adapter->getRecentLogs(50);
        $this->assertIsArray($logs);
        
        foreach ($logs as $log) {
            $this->assertArrayHasKey('level', $log);
            $this->assertArrayHasKey('message', $log);
        }
    }

    public function testLogsPanelSearch(): void
    {
        $logAdapter = new LogAdapter();
        $panel = new LogsPanel($logAdapter);
        
        // Test search mode
        $this->assertFalse($panel->isInSearchMode());
        
        $panel->enterSearchMode();
        $this->assertTrue($panel->isInSearchMode());
        
        $panel->addSearchChar('e');
        $panel->addSearchChar('r');
        $panel->addSearchChar('r');
        
        $panel->removeSearchChar();
        
        $panel->exitSearchMode();
        $this->assertFalse($panel->isInSearchMode());
    }
}