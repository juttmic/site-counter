<?php

namespace App\Http\Controllers;

use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class GifController extends Controller
{
    public function show(int $userId, string $filename, TimerGifGenerator $generator)
    {
        $path = public_path("timers/{$userId}/{$filename}");

        if (!File::exists($path)) {
            // Try to find timer by filename prefix (we store random filenames, but URL is saved on timer)
            $timer = Timer::where('user_id', $userId)
                ->where('gif_url', 'like', "%/{$userId}/{$filename}")
                ->first();

            if (!$timer) {
                abort(404);
            }

            // Regenerate if missing
            $dir = dirname($path);
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $generator->regenerateForCache($timer, $path);
        }

        // Cache handling
        $cacheKey = $this->buildCacheKey($userId, $filename);
        $ttlSeconds = $this->computeTtlSeconds($path);

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($path) {
            return response()->file($path, ['Content-Type' => 'image/gif']);
        });
    }

    private function buildCacheKey(int $userId, string $filename): string
    {
        return 'timer_' . $userId . '_' . md5($filename);
    }

    private function computeTtlSeconds(string $path): int
    {
        // Default 60 seconds cache; if file older than a minute, keep short cache
        return 60;
    }
}


