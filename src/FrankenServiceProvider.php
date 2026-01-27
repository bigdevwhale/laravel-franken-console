<?php

declare(strict_types=1);

namespace Franken\Console;

use Illuminate\Support\ServiceProvider;

class FrankenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/franken.php', 'franken');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\FrankenCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/franken.php' => config_path('franken.php'),
            ], 'franken-config');
        }
    }
}