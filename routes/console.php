<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:work-gifs-once {--timeout=120} {--tries=3} {--max-jobs=5} {--parallel=3}', function () {
    $timeout = (int) $this->option('timeout');
    $tries = (int) $this->option('tries');
    $maxJobs = max(1, (int) $this->option('max-jobs'));
    $parallel = max(1, min(10, (int) $this->option('parallel')));

    // One lock per batch run: job overrun is bounded by Laravel's per-job --timeout, not max-jobs × timeout.
    $lockTtl = $timeout + 60;

    $lock = null;
    $slot = null;
    for ($i = 0; $i < $parallel; $i++) {
        $candidate = Cache::lock("gif-queue-worker-slot-{$i}", $lockTtl);
        if ($candidate->get()) {
            $lock = $candidate;
            $slot = $i;
            break;
        }
    }

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
        $exitCode = Artisan::call('queue:work', [
            '--max-jobs' => (string) $maxJobs,
            '--queue' => 'gifs',
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
        optional($lock)->release();
    }
})->purpose('Process GIF queue (up to --max-jobs per run; up to --parallel concurrent batches; cron-safe)');
