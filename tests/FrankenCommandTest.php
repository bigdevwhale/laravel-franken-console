<?php

declare(strict_types=1);

namespace Franken\Console\Tests;

use Orchestra\Testbench\TestCase;
use Franken\Console\FrankenServiceProvider;

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
        $queueAdapter = new \Franken\Console\Adapters\QueueAdapter();
        $this->assertIsArray($queueAdapter->getQueueStats());

        $logAdapter = new \Franken\Console\Adapters\LogAdapter();
        $this->assertIsArray($logAdapter->getRecentLogs());

        $cacheAdapter = new \Franken\Console\Adapters\CacheAdapter();
        $this->assertIsArray($cacheAdapter->getCacheStats());

        $metricsAdapter = new \Franken\Console\Adapters\MetricsAdapter();
        $this->assertIsArray($metricsAdapter->getMetrics());
    }

    public function testPanelsRender(): void
    {
        $queueAdapter = new \Franken\Console\Adapters\QueueAdapter();
        $logAdapter = new \Franken\Console\Adapters\LogAdapter();
        $cacheAdapter = new \Franken\Console\Adapters\CacheAdapter();
        $metricsAdapter = new \Franken\Console\Adapters\MetricsAdapter();

        $overview = new \Franken\Console\UI\OverviewPanel();
        $this->assertIsString($overview->render());

        $queues = new \Franken\Console\UI\QueuesPanel($queueAdapter);
        $this->assertIsString($queues->render());

        $jobs = new \Franken\Console\UI\JobsPanel($queueAdapter);
        $this->assertIsString($jobs->render());

        $logs = new \Franken\Console\UI\LogsPanel($logAdapter);
        $this->assertIsString($logs->render());

        $cache = new \Franken\Console\UI\CacheConfigPanel($cacheAdapter);
        $this->assertIsString($cache->render());

        $scheduler = new \Franken\Console\UI\SchedulerPanel();
        $this->assertIsString($scheduler->render());

        $metrics = new \Franken\Console\UI\MetricsPanel($metricsAdapter);
        $this->assertIsString($metrics->render());

        $shell = new \Franken\Console\UI\ShellExecPanel();
        $this->assertIsString($shell->render());

        $settings = new \Franken\Console\UI\SettingsPanel();
        $this->assertIsString($settings->render());
    }
}