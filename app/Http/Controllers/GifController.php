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
        $path = public_path("timers/{$userId}/{$filename}.gif");

        // Resolve timer by stored gif_url
        $timer = Timer::where('user_id', $userId)
            ->where('gif_url', 'like', "%/{$userId}/{$filename}.gif")
            ->first();

        // For public endpoints, show a friendly "unavailable" page when the timer
        // doesn't exist or is inactive (instead of a hard 404).
        if (!$timer || !$timer->isActive()) {
            return response()
                ->view('timers.unavailable', [
                    'startUrl' => url('/login'),
                ], 410);
        }

        // Ensure output directory
        $dir = dirname($path);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if ($timer->isFixedDate()) {
            // Regenerate on each request for fixed-date timers (fresh remaining time)
            $generator->regenerateForCache($timer, $path);
        } else {
            // Reload-based timers: keep previous behavior
            if (!File::exists($path)) {
                $generator->regenerateForCache($timer, $path);
            }
        }

        $gifUrl = "/timers/{$userId}/{$filename}.gif";
        return redirect($gifUrl);
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


