<?php

declare(strict_types=1);

namespace Franken\Console\Adapters;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class CacheAdapter
{
    public function getCacheStats(): array
    {
        $driver = 'unknown';
        $size = 'unknown';

        try {
            $driver = config('cache.default', 'file');
            
            // Try to get some size info based on driver
            if ($driver === 'file') {
                $cachePath = config('cache.stores.file.path', storage_path('framework/cache/data'));
                if (is_dir($cachePath)) {
                    $size = $this->getDirectorySize($cachePath);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return [
            'driver' => $driver,
            'size' => $size,
            'status' => 'active',
        ];
    }

    private function getDirectorySize(string $path): string
    {
        $bytes = 0;
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            return 'unknown';
        }

        return $this->formatBytes($bytes);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public function clearCache(): bool
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            return true;
        } catch (\Exception $e) {
            // Try direct cache clear
            try {
                Cache::flush();
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function forgetKey(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            return false;
        }
    }
}