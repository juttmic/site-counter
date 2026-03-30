<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:work-gifs-once {--timeout=120} {--tries=3}', function () {
    $timeout = (int) $this->option('timeout');
    $tries = (int) $this->option('tries');

    $lock = \Illuminate\Support\Facades\Cache::lock('queue:work-gifs-once', max(10, $timeout + 30));
    if (!$lock->get()) {
        $this->info('Another GIF worker is already running. Skipping.');
        return 0;
    }

    try {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'gifs',
            '--sleep' => 0,
            '--tries' => $tries,
            '--timeout' => $timeout,
            '--no-interaction' => true,
        ]);

        $out = trim((string) \Illuminate\Support\Facades\Artisan::output());
        if ($out !== '') {
            $this->output->write($out);
        }

        return is_int($exitCode) ? $exitCode : 0;
    } finally {
        optional($lock)->release();
    }
})->purpose('Process the GIF queue once (cron-safe)');
