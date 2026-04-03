<?php

namespace App\Jobs;

use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class GenerateTimerGif implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable, InteractsWithQueue;

    /**
     * Use the timer id only — not a serialized {@see Timer}. Laravel restores serialized models
     * during unserialize (before handle()). If that load fails, handle() never runs and nothing
     * in this job can log; passing an id defers loading until inside handle().
     */
    public function __construct(public int $timerId)
    {
        $this->onQueue('gifs');
    }
    
    /**
     * The number of times the job may be attempted (queue:work --tries should match or exceed).
     */
    public $tries = 10;

    /**
     * Seconds to wait before retrying after each failure (index = attempt zero-based after first failure).
     *
     * @var list<int>
     */
    public $backoff = [15, 30, 60, 120, 120, 180, 180, 300, 300];

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 120;

    /**
     * Ensure we don't enqueue duplicate jobs per timer while one is pending.
     */
    public function uniqueId(): string
    {
        return 'timer-gif:' . $this->timerId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::channel('gif_queue')->info('GIF job handle started', [
                'timer_id' => $this->timerId,
                'attempt' => $this->attempts(),
            ]);

            $timer = Timer::query()->find($this->timerId);
            if ($timer === null) {
                Log::channel('gif_queue')->warning('GIF job skipped (timer missing)', [
                    'timer_id' => $this->timerId,
                    'attempt' => $this->attempts(),
                ]);

                return;
            }

            // If a GIF exists and is up-to-date, skip work.
            if (!$timer->needsGifRegeneration() && !empty($timer->gif_url)) {
                Log::channel('gif_queue')->info('GIF skipped (up-to-date)', [
                    'timer_id' => $timer->id,
                    'user_id' => $timer->user_id,
                    'gif_url' => $timer->gif_url,
                    'cache_key' => $timer->cache_key,
                ]);
                return;
            }

            Log::channel('gif_queue')->info('GIF generation started', [
                'timer_id' => $timer->id,
                'user_id' => $timer->user_id,
                'timer_type' => $timer->timer_type,
                'status' => $timer->status,
            ]);

            $generator = new TimerGifGenerator();
            $gifUrl = $generator->generateAndStore($timer);

            $gifPath = $this->publicPathFromTimerGifUrl($gifUrl);
            $gifBytes = $gifPath !== null && is_file($gifPath) ? filesize($gifPath) : false;
            if ($gifPath === null || $gifBytes === false || $gifBytes < 1) {
                throw new \RuntimeException(sprintf(
                    'GIF output missing or empty after generation (url=%s, resolved_path=%s)',
                    $gifUrl,
                    $gifPath ?? '(unresolved)'
                ));
            }

            // Persist GIF URL first so cache_key can use the same second as updated_at.
            // Pairing cache_key's timestamp with updated_at avoids needsGifRegeneration()
            // staying true when time() ran a second before the row was written.
            $timer->update([
                'gif_url' => $gifUrl,
            ]);
            $timer->refresh();
            $ts = (int) $timer->updated_at->timestamp;
            $timer->timestamps = false;
            $timer->cache_key = 'gif_' . $timer->id . '_' . $ts;
            $timer->save();
            $timer->timestamps = true;
            
            Log::channel('gif_queue')->info('GIF generation finished', [
                'timer_id' => $timer->id,
                'user_id' => $timer->user_id,
                'gif_url' => $gifUrl,
                'cache_key' => $timer->cache_key,
            ]);
        } catch (\Throwable $e) {
            $this->logAttemptFailure($e);
            throw $e;
        }
    }

    /**
     * Called after the job has exhausted all retries (timeout, etc.). Each thrown attempt is
     * already logged in handle(); this records the terminal outcome without duplicating full traces.
     */
    public function failed(?\Throwable $exception = null): void
    {
        if ($exception !== null) {
            Log::channel('gif_queue')->error('GIF job exhausted retries or was not retried', [
                'timer_id' => $this->timerId,
                'max_tries' => $this->tries,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        } else {
            Log::channel('gif_queue')->error('GIF job failed permanently (no exception captured)', [
                'timer_id' => $this->timerId,
                'max_tries' => $this->tries,
            ]);
        }
    }

    /**
     * Resolve a stored gif_url (/timers/...) to an absolute path under public/.
     */
    private function publicPathFromTimerGifUrl(string $gifUrl): ?string
    {
        $trimmed = trim($gifUrl);
        if ($trimmed === '') {
            return null;
        }
        $path = parse_url($trimmed, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $trimmed;
        }
        $relative = ltrim($path, '/\\');
        if ($relative === '') {
            return null;
        }

        return public_path($relative);
    }

    private function logAttemptFailure(\Throwable $e): void
    {
        $trace = $e->getTraceAsString();
        if (strlen($trace) > 8000) {
            $trace = substr($trace, 0, 8000) . '…(truncated)';
        }

        Log::channel('gif_queue')->error('GIF generation attempt failed', [
            'timer_id' => $this->timerId,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $trace,
        ]);
    }
}
