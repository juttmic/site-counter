<?php

namespace App\Console\Commands;

use App\Services\TimerGifGenerator;
use App\Services\Skins\SkinRendererFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSkinPreviews extends Command
{
    protected $signature = 'timergif:skins:previews {--force : Regenerate even if files exist} {--all : Generate for all skins, not only those with renderers} {--skin=* : Generate for specific skins, comma separated list} {--time=* : Generate for specific time durations, comma separated list}';
    protected $description = 'Generate static preview images for skins. By default only for skins with a registered renderer.';

    public function handle(TimerGifGenerator $generator): int
    {
        $skinsDir = resource_path('timerskins');
        $outDir = public_path('skin-previews');
        File::ensureDirectoryExists($outDir);

        $files = collect(File::files($skinsDir))
            ->filter(fn($f) => strtolower($f->getExtension()) === 'json')
            ->filter(fn($f) => $this->option('skin') ? in_array(pathinfo($f->getFilename(), PATHINFO_FILENAME), $this->option('skin')) : true)
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
                // Generate short (6s) animated GIF preview using the skin renderer
                $generator->renderSkinGifPreview($skinKey, $outPath, $this->option('time') ? explode(',', $this->option('time')) : 15);
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


