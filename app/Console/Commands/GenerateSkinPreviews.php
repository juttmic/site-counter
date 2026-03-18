<?php

namespace App\Console\Commands;

use App\Services\TimerGifGenerator;
use App\Services\Skins\SkinRendererFactory;
use App\Services\SkinFileHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSkinPreviews extends Command
{
    private const DEFAULT_PREVIEW_SECONDS = 90650; // 1d 1h 10m 50s

    protected $signature = 'timergif:skins:previews {skin? : Optional single skin key to generate (e.g. skin07_teal_circles)} {--force : Regenerate even if files exist} {--all : Include skins without a registered renderer} {--skin=* : (Optional, legacy) Additional skin keys, comma-separated} {--seconds= : Override preview duration in seconds (default: 15)} {--text= : Optional custom text to render} {--text-position= : Text position: top|bottom|none} {--text-color= : Hex color for custom text (e.g. #FFFFFF)} {--text-distance= : Distance between custom text and counter (px)} {--text-mt= : Text margin-top (px)} {--text-mr= : Text margin-right (px)} {--text-mb= : Text margin-bottom (px)} {--text-ml= : Text margin-left (px)} {--out-width= : Output GIF width} {--out-height= : Output GIF height} {--time=* : (Deprecated)}';
    protected $description = 'Generate animated GIF previews for skins. If a skin key is provided, only that one is generated.';

    public function handle(TimerGifGenerator $generator): int
    {
        $skinsDir = resource_path('timerskins');
        $outDir = public_path('skin-previews');
        File::ensureDirectoryExists($outDir);

        // Determine selection of skins
        $selected = [];
        if ($arg = $this->argument('skin')) {
            $selected[] = $arg;
        }
        $legacySkins = (array) ($this->option('skin') ?? []);
        foreach ($legacySkins as $s) { $selected[] = $s; }

        $paths = SkinFileHelper::listFiles();
        $files = collect($paths)
            ->when(!empty($selected), function ($c) use ($selected) {
                $set = array_map(fn($k) => trim($k), $selected);
                return $c->filter(function ($path) use ($set) {
                    return in_array(pathinfo($path, PATHINFO_FILENAME), $set, true);
                });
            })
            ->values();

        if ($files->isEmpty()) {
            $this->warn('No skin JSON/JSONC files found.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $count = 0;

        $onlyWithRenderer = ! (bool) $this->option('all');

        foreach ($files as $filePath) {
            $skinKey = pathinfo($filePath, PATHINFO_FILENAME);
            $json = SkinFileHelper::load($filePath);
            if ($json === null) {
                $this->error("Skip {$skinKey}: invalid JSON");
                continue;
            }
            $style = $json['style'] ?? null;
            if ($onlyWithRenderer) {
                $renderer = SkinRendererFactory::resolve($style);
                if (!$renderer) {
                    $this->line("Skip (no renderer): {$skinKey}" . ($style ? " [{$style}]" : ''));
                    continue;
                }
            }

            $outPath = $outDir . DIRECTORY_SEPARATOR . $skinKey . '.gif';
            if (!$force && File::exists($outPath)) {
                $this->line("Skip existing: {$skinKey}");
                continue;
            }
            try {
                // Determine starting countdown time and animation duration
                $secondsOpt = $this->option('seconds');
                // Backward compatibility: allow --time=NN or --time=NN (legacy array) to specify starting time
                $legacyTime = $this->option('time');
                if (is_array($legacyTime) && count($legacyTime) > 0) {
                    $startTime = (int) ($legacyTime[0] ?? self::DEFAULT_PREVIEW_SECONDS);
                } else if (is_string($legacyTime) && $legacyTime !== '') {
                    $startTime = (int) $legacyTime;
                } else {
                    $startTime = self::DEFAULT_PREVIEW_SECONDS;
                }
                
                // Animation duration: use --seconds option if provided, otherwise default to 30
                $animateDuration = null;
                if ($secondsOpt !== null && $secondsOpt !== false && $secondsOpt !== '') {
                    $animateDuration = (int) $secondsOpt;
                }

                $text = $this->option('text');
                $textPos = $this->option('text-position') ?: 'none';
                $textColor = $this->option('text-color');
                if (!in_array($textPos, ['top','bottom','none'], true)) { $textPos = 'none'; }

                // Collect optional overrides
                $overrides = [];
                $distance = $this->option('text-distance');
                if ($distance !== null && $distance !== '') {
                    $overrides['customTextDistance'] = (int) $distance;
                } else {
                    foreach ([['customTextMarginTop','text-mt'],['customTextMarginRight','text-mr'],['customTextMarginBottom','text-mb'],['customTextMarginLeft','text-ml']] as [$key,$opt]) {
                        $val = $this->option($opt);
                        if ($val !== null && $val !== '') { $overrides[$key] = (int) $val; }
                    }
                }
                $outW = $this->option('out-width');
                $outH = $this->option('out-height');
                if ($outW !== null && $outW !== '') { $overrides['outputWidth'] = (int) $outW; }
                if ($outH !== null && $outH !== '') { $overrides['outputHeight'] = (int) $outH; }

                // Generate animated GIF preview using the skin renderer (with optional custom text)
                // Pass startTime as starting countdown, and animateDuration for animation length
                $generator->renderSkinGifPreview($skinKey, $outPath, max(1, $startTime), $text ?: null, $textPos, $textColor ?: null, $overrides, $animateDuration);
                $this->info("Generated preview: {$skinKey}");
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed: {$skinKey} - " . $e->getMessage());
            }
        }

        $this->info("Done. Generated {$count} previews in {$outDir}.");
        return self::SUCCESS;
    }
}
