<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "inverted_pill_labels"
 * Renders rounded filled digit tiles (dark background) with large white digits,
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
    private function measureText(string $font, int $size, string $text): array
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        return [
            'minX' => $minX,
            'maxX' => $maxX,
            'minY' => $minY,
            'maxY' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
            'ascent' => -$minY,
            'descent' => $maxY,
        ];
    }

    private function fitFontSizeToBox(string $font, int $desiredSize, string $sampleText, int $maxW, int $maxH, int $minSize = 8): int
    {
        $size = max($minSize, $desiredSize);
        while ($size > $minSize) {
            $m = $this->measureText($font, $size, $sampleText);
            if ($m['width'] <= $maxW && $m['height'] <= $maxH) {
                break;
            }
            $size--;
        }
        return $size;
    }

    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Geometry: respect visibility toggles (showDays, showSeconds; hours/minutes always shown)
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0) + ($showHours ? 1 : 0) + ($showMinutes ? 1 : 0) + ($showSeconds ? 1 : 0);
        if ($segments < 1) {
            $segments = 1;
        }
        // Layout constants (must match renderTimeText).
        $gapX = max(2, (int) round($width * 0.004));
        $innerGap = max(4, (int) round($width * 0.007));
        $padTop = max(6, (int) round($height * 0.075));
        $labelArea = max(18, (int) round($height * 0.21));
        $segW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $digitTileH = max(24, $height - $labelArea - $padTop);

        $availableTileW = (int) floor(($segW - $innerGap) / 2);
        $availableTileH = $digitTileH;
        $tileW = (int) floor($availableTileW * 0.88);
        $tileH = (int) floor($availableTileH * 0.88);

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

            // Center the digit tiles within the segment
            $segCenterX = $segX1 + ($segW / 2);
            $segCenterY = $segY1 + ($digitTileH / 2);
            
            // Left tile: fixed width, full digit-row height (taller than square when width-limited)
            $leftX1 = (int) ($segCenterX - $tileW - ($innerGap / 2));
            $leftX2 = $leftX1 + $tileW;
            $leftY1 = (int) ($segCenterY - ($tileH / 2));
            $leftY2 = $leftY1 + $tileH;

            // Right tile: same dimensions as left
            $rightX1 = (int) ($segCenterX + ($innerGap / 2));
            $rightX2 = $rightX1 + $tileW;
            $rightY1 = $leftY1;
            $rightY2 = $leftY2;

            $r = max(6, (int) round(min($tileW, $tileH) * 0.02));

            // Left digit tile (rounded rect)
            imagefilledrectangle($im, $leftX1 + $r, $leftY1, $leftX2 - $r, $leftY2, $tileCol);
            imagefilledrectangle($im, $leftX1, $leftY1 + $r, $leftX2, $leftY2 - $r, $tileCol);
            imagefilledellipse($im, $leftX1 + $r, $leftY1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX2 - $r, $leftY1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX1 + $r, $leftY2 - $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX2 - $r, $leftY2 - $r, $r * 2, $r * 2, $tileCol);

            // Right digit tile (rounded rect)
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
                $cx = $gapX + ($i + 1) * $segW + $i * $gapX + (int) ($gapX / 2);
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

        $requestedFontSize  = (int)($skin['fontSize'] ?? 32);
        $requestedLabelSize = max(10, (int) round($requestedFontSize * 0.52));

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
        $gapX = max(2, (int) round($width * 0.004));
        $innerGap = max(4, (int) round($width * 0.007));
        $padTop = max(6, (int) round($height * 0.075));
        $labelArea = max(18, (int) round($height * 0.21));
        $segW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $digitTileH = max(24, $height - $labelArea - $padTop);

        $availableTileW = (int) floor(($segW - $innerGap) / 2);
        $availableTileH = $digitTileH;
        $tileW = (int) floor($availableTileW * 0.88);
        $tileH = (int) floor($availableTileH * 0.88);

        $digitsCenterY = (int) ($padTop + $digitTileH * 0.50);

        // Fit digit font to tile box so large font sizes don't overlap or overflow.
        // Use '8' as a worst-case width/height sample for most fonts.
        $tileInsetW = max(2, (int) round($tileW * 0.10));
        $tileInsetH = max(2, (int) round($tileH * 0.10));
        $digitMaxW = max(1, $tileW - ($tileInsetW * 2));
        $digitMaxH = max(1, $tileH - ($tileInsetH * 2));
        $fontSize = $this->fitFontSizeToBox($font, $requestedFontSize, '8', $digitMaxW, $digitMaxH, 8);

        // Fit label size to the reserved label area and segment width.
        $labelGapY = max(3, (int) round($height * 0.022));
        $labelMaxH = max(1, $height - ($padTop + $digitTileH) - $labelGapY - 6);
        $labelSize = $this->fitFontSizeToBox($font, $requestedLabelSize, 'SECONDS', max(1, $segW - 6), $labelMaxH, 8);

        for ($i = 0; $i < $segments; $i++) {
            $segX1 = $gapX + $i * ($segW + $gapX);
            $segCenterX = $segX1 + ($segW / 2);

            // Render each digit in its own tile (centered; height uses full digit row)
            $leftX1 = (int) ($segCenterX - $tileW - ($innerGap / 2));
            $leftX2 = $leftX1 + $tileW;
            $rightX1 = (int) ($segCenterX + ($innerGap / 2));
            $rightX2 = $rightX1 + $tileW;
            $tileCenterY = (int) ($padTop + $digitTileH * 0.50);
            $tileY1 = (int) ($tileCenterY - ($tileH / 2));
            $tileY2 = $tileY1 + $tileH;

            $num = sprintf('%02d', $units[$i]['value']);
            $leftDigit  = $num[0];
            $rightDigit = $num[1];

            // Center digits individually inside each tile
            // Calculate tile centers
            $leftTileCenterX = $leftX1 + ($tileW / 2);
            $rightTileCenterX = $rightX1 + ($tileW / 2);
            
            // Left digit centering
            $leftM = $this->measureText($font, $fontSize, $leftDigit);
            $leftX = (int) round($leftTileCenterX - ($leftM['width'] / 2) - $leftM['minX']);
            $leftBaselineY = (int) round($tileCenterY + ($leftM['height'] / 2) - $leftM['maxY']);
            imagettftext($im, $fontSize, 0, $leftX, $leftBaselineY, $textCol, $font, $leftDigit);
            
            // Right digit centering
            $rightM = $this->measureText($font, $fontSize, $rightDigit);
            $rightX = (int) round($rightTileCenterX - ($rightM['width'] / 2) - $rightM['minX']);
            $rightBaselineY = (int) round($tileCenterY + ($rightM['height'] / 2) - $rightM['maxY']);
            imagettftext($im, $fontSize, 0, $rightX, $rightBaselineY, $textCol, $font, $rightDigit);

            // Red label under the digit tiles (centered under the whole segment)
            // Position label based on actual bottom of tiles plus small gap
            $label = (string)$units[$i]['label'];
            $labelFs = $labelSize;
            // If a particular label is too wide for the segment, shrink just for that label.
            while ($labelFs > 8) {
                $lm = $this->measureText($font, $labelFs, $label);
                if ($lm['width'] <= max(1, $segW - 6) && $lm['height'] <= $labelMaxH) {
                    $lblX = (int) round($segCenterX - ($lm['width'] / 2) - $lm['minX']);
                    $lblBaselineY = (int) round($tileY2 + $labelGapY + $lm['ascent']);
                    // Keep label inside canvas
                    $lblBaselineY = min($lblBaselineY, $height - 4);
                    imagettftext($im, $labelFs, 0, $lblX, $lblBaselineY, $accentCol, $font, $label);
                    break;
                }
                $labelFs--;
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


