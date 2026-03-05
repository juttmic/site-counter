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
        // Google Fonts regular TTFs (basic selection)
        'http://fonts.gstatic.com/s/abeezee/v9/mE5BOuZKGln_Ex0uYKpIaw.ttf',
        'http://fonts.gstatic.com/s/abel/v6/RpUKfqNxoyNe_ka23bzQ2A.ttf',
        'http://fonts.gstatic.com/s/abhayalibre/v1/zTLc5Jxv6yvb1nHyqBasVy3USBnSvpkopQaUR-2r7iU.ttf',
        'http://fonts.gstatic.com/s/abrilfatface/v8/X1g_KwGeBV3ajZIXQ9VnDojjx0o0jr6fNXxPgYh_a8Q.ttf',
        'http://fonts.gstatic.com/s/aclonica/v6/M6pHZMPwK3DiBSlo3jwAKQ.ttf',
        'http://fonts.gstatic.com/s/acme/v5/-J6XNtAHPZBEbsifCdBt-g.ttf',
        'http://fonts.gstatic.com/s/actor/v6/ugMf40CrRK6Jf6Yz_xNSmQ.ttf',
        'http://fonts.gstatic.com/s/adamina/v8/RUQfOodOMiVVYqFZcSlT9w.ttf',
        'http://fonts.gstatic.com/s/adventpro/v4/1NxMBeKVcNNH2H46AUR3wfesZW2xOQ-xsNqO47m55DA.ttf',
        'http://fonts.gstatic.com/s/aguafinascript/v5/65g7cgMtMGnNlNyq_Z6CvMxLhO8OSNnfAp53LK1_iRs.ttf',
        'http://fonts.gstatic.com/s/akronim/v5/qA0L2CSArk3tuOWE1AR1DA.ttf',
        'http://fonts.gstatic.com/s/aladin/v5/PyuJ5cVHkduO0j5fAMKvAA.ttf',
        'http://fonts.gstatic.com/s/aldrich/v6/kMMW1S56gFx7RP_mW1g-Eg.ttf',
        'http://fonts.gstatic.com/s/alef/v6/ENvZ_P0HBDQxNZYCQO0lUA.ttf',
        'http://fonts.gstatic.com/s/alegreya/v7/62J3atXd6bvMU4qO_ca-eA.ttf',
        'http://fonts.gstatic.com/s/alegreyasc/v6/3ozeFnTbygMK6PfHh8B-iqCWcynf_cDxXwCLxiixG1c.ttf',
        'http://fonts.gstatic.com/s/alegreyasans/v3/KYNzioYhDai7mTMnx_gDgn8f0n03UdmQgF_CLvNR2vg.ttf',
        'http://fonts.gstatic.com/s/alegreyasanssc/v3/6kgb6ZvOagoVIRZyl8XV-EklWX-XdLVn1WTiuGuvKIU.ttf',
        'http://fonts.gstatic.com/s/alexbrush/v6/ooh3KJFbKJSUoIRWfiu8o_esZW2xOQ-xsNqO47m55DA.ttf',
        'http://fonts.gstatic.com/s/alfaslabone/v5/Qx6FPcitRwTC_k88tLPc-Yjjx0o0jr6fNXxPgYh_a8Q.ttf',
        'http://fonts.gstatic.com/s/alice/v7/wZTAfivekBqIg-rk63nFvQ.ttf',
        'http://fonts.gstatic.com/s/alike/v8/Ho8YpRKNk_202fwDiGNIyw.ttf',
        'http://fonts.gstatic.com/s/alikeangular/v6/OpeCu4xxI3qO1C7CZcJtPT3XH2uEnVI__ynTBvNyki8.ttf',
        'http://fonts.gstatic.com/s/allan/v7/T3lemhgZmLQkQI2Qc2bQHA.ttf',
        'http://fonts.gstatic.com/s/allerta/v7/s9FOEuiJFTNbMe06ifzV8g.ttf',
        'http://fonts.gstatic.com/s/allertastencil/v7/CdSZfRtHbQrBohqmzSdDYFf2eT4jUldwg_9fgfY_tHc.ttf',
        'http://fonts.gstatic.com/s/allura/v4/4hcqgZanyuJ2gMYWffIR6A.ttf',
        'http://fonts.gstatic.com/s/almendra/v8/PDpbB-ZF7deXAAEYPkQOeg.ttf',
        'http://fonts.gstatic.com/s/almendradisplay/v6/2Zuu97WJ_ez-87yz5Ai8fF6uyC_qD11hrFQ6EGgTJWI.ttf',
        'http://fonts.gstatic.com/s/almendrasc/v6/IuiLd8Fm9I6raSalxMoWeaCWcynf_cDxXwCLxiixG1c.ttf',
        'http://fonts.gstatic.com/s/amarante/v4/2dQHjIBWSpydit5zkJZnOw.ttf',
        'http://fonts.gstatic.com/s/amaranth/v6/7VcBog22JBHsHXHdnnycTA.ttf',
        'http://fonts.gstatic.com/s/amaticsc/v8/MldbRWLFytvqxU1y81xSVg.ttf',
        'http://fonts.gstatic.com/s/amethysta/v4/1jEo9tOFIJDolAUpBnWbnA.ttf',
        'http://fonts.gstatic.com/s/amiko/v1/A7bjc3cOLJtGgpPGnxyHsw.ttf',
        'http://fonts.gstatic.com/s/amiri/v7/ATARrPmSew75SlpOw2YABQ.ttf',
        'http://fonts.gstatic.com/s/amita/v1/RhdhGBXSJqkHo6g7miTEcQ.ttf',
        'http://fonts.gstatic.com/s/anaheim/v4/t-z8aXHMpgI2gjN_rIflKA.ttf',
        'http://fonts.gstatic.com/s/andada/v7/rSFaDqNNQBRw3y19MB5Y4w.ttf',
        'http://fonts.gstatic.com/s/andika/v7/oe-ag1G0lcqZ3IXfeEgaGg.ttf',
        'http://fonts.gstatic.com/s/angkor/v8/DLpLgIS-8F10ecwKqCm95Q.ttf',
        'http://fonts.gstatic.com/s/annieuseyourtelescope/v6/2cuiO5VmaR09C8SLGEQjGqbp7mtG8sPlcZvOaO8HBak.ttf',
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

