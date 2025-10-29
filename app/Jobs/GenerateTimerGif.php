<?php

namespace App\Jobs;

use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTimerGif implements ShouldQueue
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
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $generator = new TimerGifGenerator();
            $gifUrl = $generator->generateAndStore($this->timer);
            
            // Update the timer with the generated GIF URL
            $this->timer->update([
                'gif_url' => $gifUrl,
                'cache_key' => 'gif_' . $this->timer->id . '_' . time()
            ]);
            
            Log::info("Generated GIF for timer {$this->timer->id}: {$gifUrl}");
        } catch (\Exception $e) {
            Log::error("Failed to generate GIF for timer {$this->timer->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
