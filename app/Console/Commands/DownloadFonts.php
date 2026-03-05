<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class DownloadFonts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *  php artisan timergif:fonts:download
     */
    protected $signature = 'timergif:fonts:download';

    /**
     * The console command description.
     */
    protected $description = 'Download font files from a predefined list of URLs into public/assets/fonts';

    /**
     * List of font URLs to download.
     * Update this array with the URLs you want to fetch.
     *
     * If you want to override the filename, use:
     *   'https://example.com/path/font.ttf' => 'my-font.ttf'
     *
     * If you want to keep the remote basename, just use a numeric array:
     *   'https://example.com/path/font.ttf'
     */
    protected array $fontUrls = [
        // 'https://example.com/path/to/font1.ttf',
        // 'https://example.com/path/to/font2.otf' => 'CustomName.otf',
    ];

    public function handle(): int
    {
        if (empty($this->fontUrls)) {
            $this->warn('No font URLs configured. Edit DownloadFonts::$fontUrls to add URLs.');
            return self::SUCCESS;
        }

        $targetDir = public_path('assets/fonts');
        File::ensureDirectoryExists($targetDir);

        foreach ($this->fontUrls as $key => $value) {
            $url = is_int($key) ? $value : $key;
            $overrideName = is_int($key) ? null : $value;

            $this->info("Downloading: {$url}");

            try {
                $response = Http::timeout(60)->get($url);
            } catch (\Throwable $e) {
                $this->error("  Failed to request URL: {$e->getMessage()}");
                continue;
            }

            if (!$response->ok()) {
                $this->error("  HTTP error {$response->status()} while downloading.");
                continue;
            }

            $filename = $overrideName;
            if (!$filename) {
                $path = parse_url($url, PHP_URL_PATH);
                $basename = $path ? basename($path) : null;
                if (!$basename || $basename === '/' || $basename === '.') {
                    $this->error('  Could not determine filename from URL. Skipping.');
                    continue;
                }
                $filename = $basename;
            }

            $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;
            try {
                File::put($destination, $response->body());
                $this->info("  Saved to: {$destination}");
            } catch (\Throwable $e) {
                $this->error("  Failed to save file: {$e->getMessage()}");
                continue;
            }
        }

        $this->info('Font download process finished.');
        return self::SUCCESS;
    }
}

