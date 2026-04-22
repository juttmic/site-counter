<?php

namespace App\Http\Controllers;

use App\Models\Timer;
use App\Models\User;
use App\Services\TimerGifGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GifController extends Controller
{
    public function show(int $userId, string $filename, TimerGifGenerator $generator)
    {
        $path = public_path("timers/{$userId}/{$filename}.gif");
        // This endpoint returns binary image data. Ensure no PHP warnings/notices leak into the output.
        // We'll capture them into a dedicated log channel instead.
        $prevDisplayErrors = ini_set('display_errors', '0');
        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        $prevHandler = set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0) use ($userId, $filename, $path) {
            try {
                Log::channel('gif_embed')->warning('php_warning', [
                    'severity' => $severity,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'user_id' => $userId,
                    'filename' => $filename,
                    'path' => $path,
                ]);
            } catch (\Throwable $e) {
                // never break binary response
            }
            return true; // handled (prevents output)
        });

        $response = null;

        try {
            // Resolve timer by stored gif_url
            $timer = Timer::where('user_id', $userId)
                ->where('gif_url', 'like', "%/{$userId}/{$filename}.gif")
                ->first();

            // Public endpoint: if the timer can't be resolved, show a friendly page.
            if (!$timer) {
                $empireUrl = $this->buildEmpireUrl($userId);
                $response = response()
                    ->view('timers.unavailable', [
                        'startUrl' => url('/login'),
                        'empireUrl'=>$empireUrl
                    ], 410);
                return $response;
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
                    $response = response()
                        ->view('timers.unavailable', [
                            'startUrl' => url('/login'),
                            'empireUrl'=>$empireUrl
                        ], 410);
                    return $response;
                }
                $response = response()->file($path, [
                    'Content-Type' => 'image/gif',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]);
                return $response;
            }

        if ($timer->isFixedDate()) {
            // Regenerate on each request for fixed-date timers (fresh remaining time)
            $t0 = microtime(true);
            Log::channel('gif_embed')->info('embed_regen_start', [
                'timer_id' => $timer->id,
                'user_id' => $timer->user_id,
                'skin' => $timer->skin,
                'timer_type' => $timer->timer_type,
                'transparent' => (bool) (($timer->skin_overrides['transparentBackground'] ?? false) === true),
                'path' => $path,
                'exists_before' => File::exists($path),
                'bytes_before' => File::exists($path) ? @filesize($path) : null,
            ]);
            try {
                $generator->regenerateForCache($timer, $path);
            } catch (\Throwable $e) {
                Log::channel('gif_embed')->error('embed_regen_error', [
                    'timer_id' => $timer->id,
                    'path' => $path,
                    'exception_class' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw $e;
            }
            Log::channel('gif_embed')->info('embed_regen_done', [
                'timer_id' => $timer->id,
                'path' => $path,
                'exists_after' => File::exists($path),
                'bytes_after' => File::exists($path) ? @filesize($path) : null,
                'ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);
            $this->logGifFileHealth($timer->id, $path);
        } else {
            // Reload-based timers: only generate once if missing
            if (!File::exists($path)) {
                $t0 = microtime(true);
                Log::channel('gif_embed')->info('embed_regen_start', [
                    'timer_id' => $timer->id,
                    'user_id' => $timer->user_id,
                    'skin' => $timer->skin,
                    'timer_type' => $timer->timer_type,
                    'transparent' => (bool) (($timer->skin_overrides['transparentBackground'] ?? false) === true),
                    'path' => $path,
                    'exists_before' => false,
                    'bytes_before' => null,
                ]);
                try {
                    $generator->regenerateForCache($timer, $path);
                } catch (\Throwable $e) {
                    Log::channel('gif_embed')->error('embed_regen_error', [
                        'timer_id' => $timer->id,
                        'path' => $path,
                        'exception_class' => $e::class,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    throw $e;
                }
                Log::channel('gif_embed')->info('embed_regen_done', [
                    'timer_id' => $timer->id,
                    'path' => $path,
                    'exists_after' => File::exists($path),
                    'bytes_after' => File::exists($path) ? @filesize($path) : null,
                    'ms' => (int) round((microtime(true) - $t0) * 1000),
                ]);
                $this->logGifFileHealth($timer->id, $path);
            }
        }

            $response = response()->file($path, [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
            return $response;
        } finally {
            if (is_callable($prevHandler)) {
                restore_error_handler();
            }
            if ($prevDisplayErrors !== false) {
                ini_set('display_errors', (string) $prevDisplayErrors);
            }
            error_reporting($prevErrorReporting);
        }
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

    private function logGifFileHealth(int $timerId, string $path): void
    {
        try {
            if (!is_file($path)) {
                Log::channel('gif_embed')->warning('embed_file_missing', [
                    'timer_id' => $timerId,
                    'path' => $path,
                ]);
                return;
            }
            $bytes = @filesize($path);
            $fh = @fopen($path, 'rb');
            if (!$fh) {
                Log::channel('gif_embed')->warning('embed_file_unreadable', [
                    'timer_id' => $timerId,
                    'path' => $path,
                    'bytes' => $bytes,
                ]);
                return;
            }
            $head = (string) @fread($fh, 6);
            @fseek($fh, -1, SEEK_END);
            $tail = (string) @fread($fh, 1);
            @fclose($fh);

            Log::channel('gif_embed')->info('embed_file_health', [
                'timer_id' => $timerId,
                'path' => $path,
                'bytes' => $bytes,
                'head_hex' => strtoupper(bin2hex($head)),
                'tail_hex' => strtoupper(bin2hex($tail)),
                'looks_like_gif' => str_starts_with($head, 'GIF8'),
                'has_trailer' => $tail === "\x3B",
            ]);
        } catch (\Throwable $e) {
            // never break the embed response
        }
    }
}


