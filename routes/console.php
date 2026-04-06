<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command("queue:work-gifs-once")->everyThirtySeconds();
Artisan::command('queue:work-gifs-once {--timeout=120} {--tries=10} {--max-jobs=5} {--parallel=3}', function () {
    Log::channel('gif_queue')->info('Time here : ', [now()]);
    $timeout = (int) $this->option('timeout');
    $tries = max(1, (int) $this->option('tries'));
    // max-jobs=0 means "run forever" in queue:work — never pass 0 from cron.
    $maxJobs = max(1, min(100, (int) $this->option('max-jobs')));
    $parallel = max(1, min(10, (int) $this->option('parallel')));

    // Lock must cover a full batch: up to max-jobs × timeout per worker, not a single job's timeout.
    $lockTtl = max(300, $maxJobs * $timeout + 180);

    $lock = null;
    $slot = null;
    for ($i = 0; $i < $parallel; $i++) {
        $candidate = Cache::lock("gif-queue-worker-slot-{$i}", $lockTtl);
        if ($candidate->get()) {
            Log::channel("fixer")->info("Fixed : ", [$candidate, $lockTtl, $i]);
            $lock = $candidate;
            $slot = $i;
            break;
        }
    }
Log::channel("fixer")->info("Fixed : ", [$lock, $lockTtl]);
    if ($lock === null) {
        $this->info("All {$parallel} GIF worker slots are busy. Skipping.");
        Log::channel('gif_queue')->info('GIF queue worker skipped (all slots busy)', [
            'parallel' => $parallel,
        ]);

        return 0;
    }

    Log::channel('gif_queue')->info('GIF queue worker started', [
        'slot' => $slot,
        'parallel_slots' => $parallel,
        'max_jobs' => $maxJobs,
        'timeout' => $timeout,
        'lock_ttl' => $lockTtl,
        'tries' => $tries,
        'at' => now()->toIso8601String(),
    ]);

    try {
        // --stop-when-empty: without it, queue:work loops forever when the queue is idle (cron never returns, lock stuck).
        $exitCode = Artisan::call('queue:work', [
            '--max-jobs' => (string) $maxJobs,
            '--queue' => 'gifs',
            '--stop-when-empty' => true,
            '--sleep' => 0,
            '--tries' => $tries,
            '--timeout' => $timeout,
            '--no-interaction' => true,
        ]);

        $out = trim((string) Artisan::output());
        if ($out !== '') {
            $this->output->write($out);
        }

        Log::channel('gif_queue')->info('GIF queue worker finished batch', [
            'slot' => $slot,
            'max_jobs' => $maxJobs,
            'exit_code' => $exitCode,
            'at' => now()->toIso8601String(),
        ]);

        return is_int($exitCode) ? $exitCode : 0;
    } finally {
        try {
            if ($lock !== null) {
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::channel('gif_queue')->warning('GIF queue lock release failed', [
                'error' => $e->getMessage(),
                'slot' => $slot,
            ]);
        }
    }
})->purpose('Process GIF queue (up to --max-jobs per run; up to --parallel concurrent batches; cron-safe)');
