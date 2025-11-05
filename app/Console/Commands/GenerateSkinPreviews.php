<?php

namespace App\Console\Commands;

use App\Services\TimerGifGenerator;
use App\Services\Skins\SkinRendererFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSkinPreviews extends Command
{
    protected $signature = 'timergif:skins:previews {skin? : Optional single skin key to generate (e.g. skin07_teal_circles)} {--force : Regenerate even if files exist} {--all : Include skins without a registered renderer} {--skin=* : (Optional, legacy) Additional skin keys, comma-separated} {--seconds= : Override preview duration in seconds (default: 15)} {--text= : Optional custom text to render} {--text-position= : Text position: top|bottom|none} {--text-color= : Hex color for custom text (e.g. #FFFFFF)} {--time=* : (Deprecated)}';
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

        $files = collect(File::files($skinsDir))
            ->filter(fn($f) => strtolower($f->getExtension()) === 'json')
            ->when(!empty($selected), function ($c) use ($selected) {
                $set = array_map(fn($k) => trim($k), $selected);
                return $c->filter(function ($f) use ($set) {
                    return in_array(pathinfo($f->getFilename(), PATHINFO_FILENAME), $set, true);
                });
            })
            ->values();

        if ($files->isEmpty()) {
            $this->warn('No skin JSON files found.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $count = 0;

        $onlyWithRenderer = ! (bool) $this->option('all');

        foreach ($files as $file) {
            $skinKey = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $json = [];
            try {
                $json = json_decode(File::get($file->getPathname()), true) ?: [];
            } catch (\Throwable $e) {
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
                // Determine duration
                $secondsOpt = $this->option('seconds');
                // Backward compatibility: allow --time=NN or --time=NN (legacy array) to specify seconds
                $legacyTime = $this->option('time');
                if (is_array($legacyTime) && count($legacyTime) > 0) {
                    $seconds = (int) ($legacyTime[0] ?? 15);
                } else if (is_string($legacyTime) && $legacyTime !== '') {
                    $seconds = (int) $legacyTime;
                } else if ($secondsOpt !== null && $secondsOpt !== false) {
                    $seconds = (int) $secondsOpt;
                } else {
                    $seconds = 15;
                }

                $text = $this->option('text');
                $textPos = $this->option('text-position') ?: 'none';
                $textColor = $this->option('text-color');
                if (!in_array($textPos, ['top','bottom','none'], true)) { $textPos = 'none'; }

                // Generate animated GIF preview using the skin renderer (with optional custom text)
                $generator->renderSkinGifPreview($skinKey, $outPath, max(1, $seconds), $text ?: null, $textPos, $textColor ?: null);
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


