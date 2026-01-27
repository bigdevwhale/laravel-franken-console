<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

use Illuminate\Support\Facades\Cache;

class CacheAdapter
{
    public function getCacheStats(): array
    {
        return [
            'driver' => Cache::getStore()->getStore(),
            'size' => 'unknown', // Hard to get size
        ];
    }

    public function clearCache(): void
    {
        try {
            if (function_exists('artisan')) {
                \Artisan::call('cache:clear');
                \Artisan::call('config:clear');
                \Artisan::call('view:clear');
                \Artisan::call('route:clear');
            }
        } catch (\Exception $e) {
            // Mock clear
        }
    }
}