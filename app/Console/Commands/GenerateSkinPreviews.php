<?php

namespace App\Console\Commands;

use App\Services\TimerGifGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSkinPreviews extends Command
{
    protected $signature = 'timergif:skins:previews {--force : Regenerate even if files exist}';
    protected $description = 'Generate static preview images for all timer skins';

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

        foreach ($files as $file) {
            $skinKey = pathinfo($file->getFilename(), PATHINFO_FILENAME);
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


