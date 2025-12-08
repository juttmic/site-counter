<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateSkinDefaults extends Command
{
    protected $signature = 'timergif:skins:update-defaults';
    protected $description = 'Update all skin JSON files with new default values: tile background #DB0000, white digits, white timer background';

    /**
     * Specific skins that need black digits (no background for digits, rendered directly on white)
     */
    private array $skinsWithBlackDigits = [
        'skin25_orbit_dots',
        'skin23_wave_underline',
        'skin22_tape_reel',
        'skin24_pennant_banner',
        'skin18_arc_gauges',
        'skin11_quad_rings',
        'skin07_teal_circles',
    ];

    /**
     * Styles that render digits on tiles/bars (accentColor or tileColor)
     * For these, white digits on colored tiles/bars are fine
     * If a style is NOT in this list and has white background + white textColor, we'll fix it
     */
    private array $stylesWithTileRendering = [
        'inverted_pill_labels',      // Uses tileColor for tiles
        'teal_square_tiles',         // Uses accentColor for tiles
        'hex_tile',                  // Uses accentColor for hex tiles
        'slate_rounded_bar',         // Uses accentColor as bar background
        'teal_pill_colon',           // Uses accentColor as pill background
        'teal_circle_badges',        // Uses accentColor for circles
        'split_flap_board',          // Uses accentColor for flaps
        'quad_ring_progress',        // Uses accentColor for rings
        'gradient_bar_stacks',       // Uses accentColor for bars
        'ribbon_tag',                // Uses accentColor for ribbons
        'arc_gauge',                 // Uses accentColor for arcs
        'staggered_stack',           // Uses accentColor for stacks
        'parallelogram_strip',       // Uses accentColor for strips
        'ticket_stub',               // Uses accentColor for tickets
        'tape_reel',                 // Uses accentColor for reels
        'wave_underline',            // Uses accentColor for waves
        'pennant_banner',            // Uses accentColor for banners
        'orbit_dots',                // Uses accentColor for dots
        'emoji_badge',               // Uses accentColor for badges
        // Add more tile/bar-based styles here
    ];

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
        $fixedVisibility = 0;
        
        foreach ($files as $file) {
            $skinKey = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            try {
                $json = json_decode(File::get($file->getPathname()), true) ?: [];
                
                $style = $json['style'] ?? 'default';
                $background = strtoupper(trim($json['background'] ?? '#FFFFFF'));
                $textColor = strtoupper(trim($json['textColor'] ?? '#FFFFFF'));
                
                // Update defaults
                $json['background'] = '#FFFFFF'; // White timer background
                
                // Update tile color - check for both accentColor and tileColor
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
                
                // Check if this is a specific skin that needs black digits
                if (in_array($skinKey, $this->skinsWithBlackDigits, true)) {
                    $json['textColor'] = '#000000'; // Black digits for these specific skins
                    $fixedVisibility++;
                    $this->line("Set black digits: {$skinKey} (style: {$style}) - changed textColor to #000000");
                } else {
                    // Check if this style uses tiles/bars (where white digits on colored tiles are fine)
                    $rendersOnTiles = in_array($style, $this->stylesWithTileRendering, true);
                    
                    // If background is white and style doesn't use tiles, digits are rendered directly on background
                    // In that case, white digits on white background would be invisible, so use red
                    if ($background === '#FFFFFF' && !$rendersOnTiles) {
                        $json['textColor'] = '#DB0000'; // Red digits for visibility on white background
                        $fixedVisibility++;
                        $this->line("Fixed visibility: {$skinKey} (style: {$style}) - changed textColor to #DB0000");
                    } else {
                        // For tile-based renderers, keep white digits (they'll be on colored tiles/bars)
                        $json['textColor'] = '#FFFFFF';  // White digits
                    }
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
        if ($fixedVisibility > 0) {
            $this->info("Fixed visibility for {$fixedVisibility} skins (changed textColor to #DB0000 for white backgrounds).");
        }
        return self::SUCCESS;
    }
}

