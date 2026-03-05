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

            $binary = $response->body();

            // Determine base filename (from override or URL basename)
            $filename = $overrideName;
            $path = parse_url($url, PHP_URL_PATH);
            $originalBasename = $path ? basename($path) : null;
            $ext = $originalBasename ? pathinfo($originalBasename, PATHINFO_EXTENSION) : 'ttf';
            if (!$filename) {
                if (!$originalBasename || $originalBasename === '/' || $originalBasename === '.') {
                    $this->error('  Could not determine filename from URL. Skipping.');
                    continue;
                }
                $filename = $originalBasename;
            }

            // Try to extract the internal font full name and rename file accordingly
            $fontName = $this->extractFontFullName($binary);
            if ($fontName) {
                // Sanitize font name for filesystem
                $safeName = preg_replace('/[^A-Za-z0-9 _-]+/', '', $fontName) ?: 'font';
                $safeName = trim(preg_replace('/\s+/', ' ', $safeName));
                $filename = $safeName . '.' . $ext;
            }

            // Ensure uniqueness to avoid overwriting existing files
            $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;
            if (File::exists($destination)) {
                $base = pathinfo($filename, PATHINFO_FILENAME);
                $counter = 1;
                do {
                    $candidate = $base . '_' . $counter++ . '.' . $ext;
                    $destination = $targetDir . DIRECTORY_SEPARATOR . $candidate;
                } while (File::exists($destination));
            }

            try {
                File::put($destination, $binary);
                $this->info("  Saved to: {$destination}");
            } catch (\Throwable $e) {
                $this->error("  Failed to save file: {$e->getMessage()}");
                continue;
            }
        }

        $this->info('Font download process finished.');
        return self::SUCCESS;
    }

    /**
     * Extract the internal "Full font name" (name ID 4) or family name (name ID 1)
     * from a TrueType/OpenType font binary.
     */
    private function extractFontFullName(string $binary): ?string
    {
        // Minimum sfnt header size
        if (strlen($binary) < 12) {
            return null;
        }

        // Offset table: sfntVersion(4), numTables(2), searchRange(2), entrySelector(2), rangeShift(2)
        $header = unpack('Nsfnt/nnumTables', substr($binary, 0, 6));
        $numTables = $header['numTables'] ?? 0;
        if ($numTables <= 0) {
            return null;
        }

        $nameTableOffset = null;
        $nameTableLength = null;
        $offset = 12; // start of table records

        for ($i = 0; $i < $numTables; $i++) {
            $record = substr($binary, $offset + $i * 16, 16);
            if (strlen($record) < 16) {
                break;
            }
            $tag = substr($record, 0, 4);
            if ($tag === 'name') {
                // Table record: tag(4), checkSum(4), offset(4), length(4)
                $info = unpack('NcheckSum/NtableOffset/NtableLength', substr($record, 4, 12));
                $nameTableOffset = $info['tableOffset'] ?? null;
                $nameTableLength = $info['tableLength'] ?? null;
                break;
            }
        }

        if ($nameTableOffset === null || $nameTableLength === null) {
            return null;
        }

        $nameData = substr($binary, $nameTableOffset, $nameTableLength);
        if (strlen($nameData) < 6) {
            return null;
        }

        // name table header: format(2), count(2), stringOffset(2)
        $nameHeader = unpack('nformat/ncount/nstringOffset', substr($nameData, 0, 6));
        $count = $nameHeader['count'] ?? 0;
        $stringStorageOffset = $nameHeader['stringOffset'] ?? 0;

        $best = null;

        for ($i = 0; $i < $count; $i++) {
            $rec = substr($nameData, 6 + $i * 12, 12);
            if (strlen($rec) < 12) {
                break;
            }
            // name record: platformID(2), encodingID(2), languageID(2), nameID(2), length(2), offset(2)
            $r = unpack('nplatformID/nencodingID/nlanguageID/nnameID/nlength/noffset', $rec);
            $nameId = $r['nameID'] ?? null;
            if ($nameId !== 4 && $nameId !== 1) {
                continue;
            }
            $len = $r['length'] ?? 0;
            $off = $r['offset'] ?? 0;
            $strOffset = $stringStorageOffset + $off;
            if ($strOffset + $len > strlen($nameData)) {
                continue;
            }
            $raw = substr($nameData, $strOffset, $len);
            if ($raw === '') {
                continue;
            }

            $candidate = null;
            // Platform 3 = Windows, usually UTF-16BE
            if (($r['platformID'] ?? 0) === 3) {
                $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $raw);
                if ($decoded !== false && $decoded !== '') {
                    $candidate = $decoded;
                }
            } else {
                $candidate = $raw;
            }

            if ($candidate !== null) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $best = $candidate;
                    // Prefer full font name (nameID 4) over family (1)
                    if ($nameId === 4) {
                        break;
                    }
                }
            }
        }

        return $best ?: null;
    }
}

