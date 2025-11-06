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

        // if (!File::exists($path)) {
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
            $generator->regenerateForCache($timer, $path, 5);
        // }

        // Cache handling
        $cacheKey = $this->buildCacheKey($userId, $filename);
        $ttlSeconds = $this->computeTtlSeconds($path);
        $gifUrl = "/timers/{$userId}/{$filename}";
        $html = <<<HTML
<!DOCTYPE html>
<html style="height:100%;">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$filename}</title>
    <style>
        body {
            margin: 0;
            height: 100%;
            background-color: rgb(14,14,14);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        img {
            display: block;
            background-color: hsl(0, 0%, 90%);
            max-width: 100%;
            height: auto;
            user-select: none;
        }
    </style>
</head>
<body>
    <img src="{$gifUrl}" alt="GIF">
</body>
</html>
HTML;

    return response($html, 200)->header('Content-Type', 'text/html');
return "<html><body><img src=\"/timers/{$userId}/{$filename}\" /><br><a href=\"/\">Back to dashboard</a></body></html>";
        // return Cache::remember($cacheKey, $ttlSeconds, function () use ($path) {
            return response()->file($path, ['Content-Type' => 'image/gif']);
        // });
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


