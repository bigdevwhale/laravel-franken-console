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

        $overview = new OverviewPanel('Overview', $terminal);
        $overview->setDimensions(80, 24);
        $this->assertIsString($overview->render());

        $queues = new QueuesPanel('Queues', $queueAdapter);
        $queues->setDimensions(80, 24);
        $this->assertIsString($queues->render());

        $jobs = new JobsPanel('Jobs', $queueAdapter);
        $jobs->setDimensions(80, 24);
        $this->assertIsString($jobs->render());

        $logs = new LogsPanel('Logs', $logAdapter);
        $logs->setDimensions(80, 24);
        $this->assertIsString($logs->render());

        $cache = new CacheConfigPanel('Cache', $cacheAdapter);
        $cache->setDimensions(80, 24);
        $this->assertIsString($cache->render());

        $scheduler = new SchedulerPanel('Scheduler');
        $scheduler->setDimensions(80, 24);
        $this->assertIsString($scheduler->render());

        $metrics = new MetricsPanel('Metrics', $metricsAdapter);
        $metrics->setDimensions(80, 24);
        $this->assertIsString($metrics->render());

        $shell = new ShellExecPanel('Shell');
        $shell->setDimensions(80, 24);
        $this->assertIsString($shell->render());

        $settings = new SettingsPanel('Settings');
        $settings->setDimensions(80, 24);
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
        $this->assertEquals('overview', strtolower($dashboard->getCurrentPanel()->getName()));

        // Test switching panels
        $dashboard->switchPanel('logs');
        $this->assertEquals('logs', strtolower($dashboard->getCurrentPanel()->getName()));

        $dashboard->switchPanel('queues');
        $this->assertEquals('queues', strtolower($dashboard->getCurrentPanel()->getName()));

        // Test next/previous
        $dashboard->switchPanel('overview');
        $dashboard->nextPanel();
        $this->assertEquals('queues', strtolower($dashboard->getCurrentPanel()->getName()));

        $dashboard->previousPanel();
        $this->assertEquals('overview', strtolower($dashboard->getCurrentPanel()->getName()));

        // Test wrap-around
        $dashboard->previousPanel();
        $this->assertEquals('settings', strtolower($dashboard->getCurrentPanel()->getName()));
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
        $panel = new LogsPanel('Logs', $logAdapter);
        
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