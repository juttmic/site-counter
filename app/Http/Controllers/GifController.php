<?php

namespace App\Http\Controllers;

use App\Models\Timer;
use App\Models\User;
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

        // Public endpoint: if the timer can't be resolved, show a friendly page.
        if (!$timer) {
            $empireUrl = $this->buildEmpireUrl($userId);
            return response()
                ->view('timers.unavailable', [
                    'startUrl' => url('/login'),
                    'empireUrl'=>$empireUrl
                ], 410);
        }

        // Ensure output directory
        $dir = dirname($path);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Inactive timers should still render the *previously generated* GIF when available,
        // especially for embed code usage. Never regenerate while inactive.
        if (!$timer->isActive()) {
            if (!File::exists($path)) {
                $empireUrl = $this->buildEmpireUrl($userId);
                return response()
                    ->view('timers.unavailable', [
                        'startUrl' => url('/login'),
                        'empireUrl'=>$empireUrl
                    ], 410);
            }
            return response()->file($path, [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        if ($timer->isFixedDate()) {
            // Regenerate on each request for fixed-date timers (fresh remaining time)
            $generator->regenerateForCache($timer, $path);
        } else {
            // Reload-based timers: only generate once if missing
            if (!File::exists($path)) {
                $generator->regenerateForCache($timer, $path);
            }
        }

        return response()->file($path, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
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


    private function buildEmpireUrl($user_id = null)
    {
        $user = User::find($user_id);
        if( !$user ) {return "https://app.empirely.ai/gotosalepage?e=";}
        $encodedEmail = base64_encode(base64_encode($user->email));
        return "https://app.empirely.ai/gotosalepage?e=" . urlencode($encodedEmail);
    }
}


