<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "inverted_pill_labels"
 * Renders four rounded filled tiles (dark background) with large white digits,
 * thin colon separators between tiles, and red full-word labels below each tile,
 * matching the provided reference screenshot.
 *
 * Recognized skin keys (with fallbacks):
 *  - width, height, background, textColor (digit color), accentColor (label/divider color)
 *  - fontPath, fontSize
 *  - tileColor (optional; defaults to #000000)
 */
class InvertedPillLabelsRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Geometry (now: 4 segments; each segment has TWO digit tiles)
        $segments = 4;
        $gapX = max(3, (int)round($width * 0.0));            // gap between segments
        $innerGap = max(6, (int)round($width * 0.012));        // gap between the two digit tiles inside a segment
        $padTop  = max(8, (int)round($height * 0.12));         // top padding above tiles
        $labelArea = max(20, (int)round($height * 0.26));      // reserved height for labels
        $segW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $digitTileH = max(24, $height - $labelArea - $padTop); // height of digit tiles area
        
        // Calculate square tile size (make them smaller and square)
        // Use 90% of available space to minimize horizontal padding within each section
        $availableTileW = (int) floor(($segW - $innerGap) / 2);
        $availableTileH = $digitTileH;
        $tileSize = (int) floor(min($availableTileW, $availableTileH) * 0.9);

        // Colors
        [$br, $bg_, $bb] = $this->hexToRgb($skin['background'] ?? '#FFFFFF'); // unused aside from $bgCol param
        [$tr, $tg, $tb]  = $this->hexToRgb($skin['textColor'] ?? '#FFFFFF'); // digits color
        [$ar, $ag, $ab]  = $this->hexToRgb($skin['accentColor'] ?? '#FF3B30'); // labels color
        [$cr, $cg, $cb]  = $this->hexToRgb($skin['tileColor'] ?? '#000000'); // tile fill
        $tileCol = imagecolorallocate($im, $cr, $cg, $cb);
        $digitCol = imagecolorallocate($im, $tr, $tg, $tb);
        $labelCol = imagecolorallocate($im, $ar, $ag, $ab);
        $colonCol = imagecolorallocate($im, 0, 0, 0); // colon in dark for contrast

        // Layout orientation
        $vertical = isset($skin['layout']) && $skin['layout'] === 'vertical';

        // Draw two rounded tiles per segment (for each digit)
        for ($i = 0; $i < $segments; $i++) {
            $segX1 = $gapX + $i * ($segW + $gapX);
            $segY1 = $padTop; // consistent top padding; for vertical we still align top and renderer will compute below
            $segX2 = $segX1 + $segW;
            $segY2 = $segY1 + $digitTileH;

            // Center the square tiles within the segment
            $segCenterX = $segX1 + ($segW / 2);
            $segCenterY = $segY1 + ($digitTileH / 2);
            
            // Left tile: centered, square
            $leftX1 = (int) ($segCenterX - $tileSize - ($innerGap / 2));
            $leftX2 = $leftX1 + $tileSize;
            $leftY1 = (int) ($segCenterY - ($tileSize / 2));
            $leftY2 = $leftY1 + $tileSize;
            
            // Right tile: centered, square
            $rightX1 = (int) ($segCenterX + ($innerGap / 2));
            $rightX2 = $rightX1 + $tileSize;
            $rightY1 = $leftY1; // Same Y as left tile
            $rightY2 = $leftY2;

            $r = max(6, (int)round($tileSize * 0.02)); // Rounded corners based on tile size

            // Left digit tile (square, rounded)
            imagefilledrectangle($im, $leftX1 + $r, $leftY1, $leftX2 - $r, $leftY2, $tileCol);
            imagefilledrectangle($im, $leftX1, $leftY1 + $r, $leftX2, $leftY2 - $r, $tileCol);
            imagefilledellipse($im, $leftX1 + $r, $leftY1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX2 - $r, $leftY1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX1 + $r, $leftY2 - $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX2 - $r, $leftY2 - $r, $r * 2, $r * 2, $tileCol);

            // Right digit tile (square, rounded)
            imagefilledrectangle($im, $rightX1 + $r, $rightY1, $rightX2 - $r, $rightY2, $tileCol);
            imagefilledrectangle($im, $rightX1, $rightY1 + $r, $rightX2, $rightY2 - $r, $tileCol);
            imagefilledellipse($im, $rightX1 + $r, $rightY1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $rightX2 - $r, $rightY1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $rightX1 + $r, $rightY2 - $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $rightX2 - $r, $rightY2 - $r, $r * 2, $r * 2, $tileCol);
        }

        // Colon separators between tiles
        $font = $skin['fontPath'] ?? '';
        $fontSize = (int)($skin['fontSize'] ?? 32);
        if (is_file($font)) {
            $sep = (string)($skin['separator'] ?? ':');
            if ($sep === '') { $sep = ':'; }
            // Use first visible character for placement calculations
            $sepChar = mb_substr($sep, 0, 1);
            // Choose separator color if provided; fallback to text color
            $sepCol = $textCol;
            if (!empty($skin['separatorColor']) && is_string($skin['separatorColor'])) {
                [$sr,$sg,$sb] = $this->hexToRgb($skin['separatorColor']);
                $sepCol = imagecolorallocate($im, $sr, $sg, $sb);
            }
            for ($i = 0; $i < $segments - 1; $i++) {
                $cx = $gapX + ($i + 1) * $segW + $i * $gapX + (int)($gapX / 2);
                $cy = (int)($padTop + $digitTileH * 0.50); // Center vertically in tile area
                $colon = $sepChar;
                $box = imagettfbbox($fontSize, 0, $font, $colon);
                // precise centering uses bbox min/max, not just width
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $maxX = max($box[0], $box[2], $box[4], $box[6]);
                $minY = min($box[1], $box[3], $box[5], $box[7]);
                $maxY = max($box[1], $box[3], $box[5], $box[7]);
                $x    = (int)($cx - ($minX + $maxX) / 2);
                $y    = (int)($cy - ($minY + $maxY) / 2); // center vertically relative to baseline
                imagettftext($im, $fontSize, 0, $x+1, $y, $sepCol, $font, $colon);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = $skin['fontPath'] ?? '';
        if (!is_file($font)) {
            return true;
        }

        $fontSize  = (int)($skin['fontSize'] ?? 32);
        $labelSize = max(12, (int) round($fontSize * 0.52));

        $days    = (int) floor($secondsRemaining / 86400);
        $hours   = (int) floor(($secondsRemaining % 86400) / 3600);
        $minutes = (int) floor(($secondsRemaining % 3600) / 60);
        $seconds = (int) ($secondsRemaining % 60);

        // Respect checkbox flags to show/hide units
        $units = [];
        if (($skin['showDays'] ?? true) === true)    { $units[] = ['value' => $days,    'label' => 'DAYS']; }
        if (($skin['showHours'] ?? true) === true)   { $units[] = ['value' => $hours,   'label' => 'HOURS']; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($units)) {
            $units = [ ['value'=>$seconds,'label'=>'SECONDS'] ];
        }

        $segments = count($units);
        $gapX = max(4, (int)round($width * 0.008));
        $innerGap = max(6, (int)round($width * 0.012));
        $padTop  = max(8, (int)round($height * 0.12));
        $labelArea = max(20, (int)round($height * 0.26));
        $segW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $digitTileH = max(24, $height - $labelArea - $padTop);
        
        // Calculate square tile size (same as in renderStyle)
        $availableTileW = (int) floor(($segW - $innerGap) / 2);
        $availableTileH = $digitTileH;
        $tileSize = (int) floor(min($availableTileW, $availableTileH) * 0.9);
        
        $digitsCenterY  = (int) ($padTop + $digitTileH * 0.50);

        for ($i = 0; $i < $segments; $i++) {
            $segX1 = $gapX + $i * ($segW + $gapX);
            $segCenterX = $segX1 + ($segW / 2);

            // Render each digit in its own square tile (centered)
            $leftX1  = (int) ($segCenterX - $tileSize - ($innerGap / 2));
            $leftX2  = $leftX1 + $tileSize;
            $rightX1 = (int) ($segCenterX + ($innerGap / 2));
            $rightX2 = $rightX1 + $tileSize;
            $tileCenterY = (int) ($padTop + $digitTileH * 0.50);
            $tileY1 = (int) ($tileCenterY - ($tileSize / 2));
            $tileY2 = $tileY1 + $tileSize;

            $num = sprintf('%02d', $units[$i]['value']);
            $leftDigit  = $num[0];
            $rightDigit = $num[1];

            // Center digits individually inside their square tiles
            // Calculate tile centers
            $leftTileCenterX = $leftX1 + ($tileSize / 2);
            $rightTileCenterX = $rightX1 + ($tileSize / 2);
            
            // Left digit centering
            $leftBox = imagettfbbox($fontSize, 0, $font, $leftDigit);
            if ($leftBox !== false) {
                // Calculate text bounding box dimensions
                $leftTextWidth = abs($leftBox[4] - $leftBox[0]);
                $leftTextHeight = abs($leftBox[1] - $leftBox[7]); // Top to bottom
                $leftTextTop = min($leftBox[1], $leftBox[3], $leftBox[5], $leftBox[7]);
                $leftTextBottom = max($leftBox[1], $leftBox[3], $leftBox[5], $leftBox[7]);
                
                // Center horizontally: adjust X so text center aligns with tile center
                // imagettftext uses baseline at Y, so we need to account for the offset
                $leftX = (int) ($leftTileCenterX - ($leftBox[0] + $leftBox[4]) / 2);
                
                // Center vertically: place baseline so visual center of text aligns with tile center
                // The visual center is at (top + bottom) / 2, but baseline is at Y=0 in bbox
                $leftTextVisualCenter = ($leftTextTop + $leftTextBottom) / 2;
                $leftBaselineY = (int) ($tileCenterY - $leftTextVisualCenter);
                
                imagettftext($im, $fontSize, 0, $leftX, $leftBaselineY, $textCol, $font, $leftDigit);
            }
            
            // Right digit centering
            $rightBox = imagettfbbox($fontSize, 0, $font, $rightDigit);
            if ($rightBox !== false) {
                // Calculate text bounding box dimensions
                $rightTextWidth = abs($rightBox[4] - $rightBox[0]);
                $rightTextHeight = abs($rightBox[1] - $rightBox[7]);
                $rightTextTop = min($rightBox[1], $rightBox[3], $rightBox[5], $rightBox[7]);
                $rightTextBottom = max($rightBox[1], $rightBox[3], $rightBox[5], $rightBox[7]);
                
                // Center horizontally
                $rightX = (int) ($rightTileCenterX - ($rightBox[0] + $rightBox[4]) / 2);
                
                // Center vertically
                $rightTextVisualCenter = ($rightTextTop + $rightTextBottom) / 2;
                $rightBaselineY = (int) ($tileCenterY - $rightTextVisualCenter);
                
                imagettftext($im, $fontSize, 0, $rightX, $rightBaselineY, $textCol, $font, $rightDigit);
            }

            // Red label under the digit tiles (centered under the whole segment)
            // Position label based on actual bottom of tiles plus small gap
            $label = (string)$units[$i]['label'];
            $lblBox = imagettfbbox($labelSize, 0, $font, $label);
            if ($lblBox !== false) {
                $lblW = abs($lblBox[4] - $lblBox[0]);
                $lblX = (int) ($segX1 + ($segW - $lblW) / 2);
                // Position label baseline just below the tiles with minimal gap (4 pixels)
                // $tileY2 is the bottom of the tiles
                $lblY = (int) ($tileY2 + 20);
                imagettftext($im, $labelSize, 0, $lblX, $lblY, $accentCol, $font, $label);
            }
        }

        return true;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}


