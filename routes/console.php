<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:work-gifs-once {--timeout=120} {--tries=3} {--max-jobs=5}', function () {
    $timeout = (int) $this->option('timeout');
    $tries = (int) $this->option('tries');
    $maxJobs = max(1, (int) $this->option('max-jobs'));

    Log::channel('gif_queue')->info('GIF queue worker started', [
        'max_jobs' => $maxJobs,
        'timeout' => $timeout,
        'tries' => $tries,
        'at' => now()->toIso8601String(),
    ]);

    // Hold lock long enough for worst case: each job may run up to $timeout seconds.
    $lockTtl = max(120, ($maxJobs * $timeout) + 90);

    $lock = \Illuminate\Support\Facades\Cache::lock('queue:work-gifs-once', $lockTtl);
    if (!$lock->get()) {
        $this->info('Another GIF worker is already running. Skipping.');
        Log::channel('gif_queue')->info('GIF queue worker skipped (lock held)');

        return 0;
    }

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
            'max_jobs' => $maxJobs,
            'exit_code' => $exitCode,
            'at' => now()->toIso8601String(),
        ]);

        return is_int($exitCode) ? $exitCode : 0;
    } finally {
        optional($lock)->release();
    }
})->purpose('Process up to five GIF queue jobs per run (cron-safe; use --max-jobs to override)');
