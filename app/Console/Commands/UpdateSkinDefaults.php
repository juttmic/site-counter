<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateSkinDefaults extends Command
{
    protected $signature = 'timergif:skins:update-defaults';
    protected $description = 'Update all skin JSON files with new default values: tile background #DB0000, white digits, white timer background';

    public function handle(): int
    {
        $skinsDir = resource_path('timerskins');
        $files = collect(File::files($skinsDir))
            ->filter(fn($f) => strtolower($f->getExtension()) === 'json')
            ->values();

        if ($files->isEmpty()) {
            $this->warn('No skin JSON files found.');
            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($files as $file) {
            $skinKey = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            try {
                $json = json_decode(File::get($file->getPathname()), true) ?: [];
                
                // Update defaults
                $json['background'] = '#FFFFFF'; // White timer background
                $json['textColor'] = '#FFFFFF';  // White digits
                
                // Update tile color - check for both accentColor and tileColor
                // accentColor is commonly used for tile color in many skins
                if (isset($json['accentColor'])) {
                    $json['accentColor'] = '#DB0000'; // Red tile background
                }
                if (isset($json['tileColor'])) {
                    $json['tileColor'] = '#DB0000'; // Red tile background
                }
                
                // If neither exists, add accentColor as it's the most common
                if (!isset($json['accentColor']) && !isset($json['tileColor'])) {
                    $json['accentColor'] = '#DB0000';
                }
                
                // Write back with pretty print
                File::put($file->getPathname(), json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
                $this->info("Updated: {$skinKey}");
                $updated++;
            } catch (\Throwable $e) {
                $this->error("Failed to update {$skinKey}: " . $e->getMessage());
            }
        }

        $this->info("Done. Updated {$updated} skin files.");
        return self::SUCCESS;
    }
}

