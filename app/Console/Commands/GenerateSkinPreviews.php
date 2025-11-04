<?php

namespace App\Console\Commands;

use App\Services\TimerGifGenerator;
use App\Services\Skins\SkinRendererFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSkinPreviews extends Command
{
    protected $signature = 'timergif:skins:previews {--force : Regenerate even if files exist} {--all : Generate for all skins, not only those with renderers}';
    protected $description = 'Generate static preview images for skins. By default only for skins with a registered renderer.';

    public function handle(TimerGifGenerator $generator): int
    {
        $skinsDir = resource_path('timerskins');
        $outDir = public_path('skin-previews');
        File::ensureDirectoryExists($outDir);

        $files = collect(File::files($skinsDir))
            ->filter(fn($f) => strtolower($f->getExtension()) === 'json')
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

            $outPath = $outDir . DIRECTORY_SEPARATOR . $skinKey . '.png';
            if (!$force && File::exists($outPath)) {
                $this->line("Skip existing: {$skinKey}");
                continue;
            }
            try {
                $generator->renderSkinPreview($skinKey, $outPath);
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


