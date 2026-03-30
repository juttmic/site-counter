<?php

namespace App\Jobs;

use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTimerGif implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $timer;
    
    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;
    
    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(Timer $timer)
    {
        $this->timer = $timer;
        // Route all GIF generation through a dedicated queue.
        $this->onQueue('gifs');
    }

    /**
     * Ensure we don't enqueue duplicate jobs per timer while one is pending.
     */
    public function uniqueId(): string
    {
        return 'timer-gif:' . $this->timer->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Always re-load latest timer state when processing.
            $timer = $this->timer->fresh();
            if (!$timer) {
                return;
            }

            // If a GIF exists and is up-to-date, skip work.
            if (!$timer->needsGifRegeneration() && !empty($timer->gif_url)) {
                return;
            }

            $generator = new TimerGifGenerator();
            $gifUrl = $generator->generateAndStore($timer);
            
            // Update the timer with the generated GIF URL
            $timer->update([
                'gif_url' => $gifUrl,
                'cache_key' => 'gif_' . $timer->id . '_' . time()
            ]);
            
            Log::info("Generated GIF for timer {$timer->id}: {$gifUrl}");
        } catch (\Exception $e) {
            Log::error("Failed to generate GIF for timer {$this->timer->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
